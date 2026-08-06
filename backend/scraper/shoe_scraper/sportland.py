from __future__ import annotations

import json
import re
import time
from datetime import datetime, timezone
from decimal import Decimal, InvalidOperation
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.parse import urlparse
from urllib.request import HTTPRedirectHandler, Request, build_opener

from .contract import ScrapeError, ScrapeResult

ALLOWED_HOSTS = {"sportland.lv"}
GRAPHQL_ENDPOINT = "https://sportland.lv/graphql"
MAX_RESPONSE_BYTES = 2 * 1024 * 1024
RETRYABLE_STATUSES = {429, 500, 502, 503, 504}

PRODUCT_QUERY = """
query ScrapeProduct($urlKey: String!) {
  products(filter: { url_key: { eq: $urlKey } }) {
    items {
      sku
      url_key
      stock_status
      price_range {
        minimum_price {
          regular_price { value currency }
          final_price { value currency }
        }
        maximum_price {
          regular_price { value currency }
          final_price { value currency }
        }
      }
      ... on ConfigurableProduct {
        configurable_options {
          attribute_code
          values { value_index label }
        }
        variants {
          product { stock_status footwear_size }
        }
      }
    }
  }
}
"""


class _SafeGraphqlRedirectHandler(HTTPRedirectHandler):
    def redirect_request(
        self,
        req: Request,
        fp: Any,
        code: int,
        msg: str,
        headers: Any,
        newurl: str,
    ) -> Request | None:
        if newurl != GRAPHQL_ENDPOINT:
            raise ScrapeError("url_not_allowed", "Sportland redirected its product API unexpectedly.")
        return super().redirect_request(req, fp, code, msg, headers, newurl)


def scrape(
    url: str,
    *,
    timeout_seconds: float = 20,
    user_agent: str = "ShoeFinderScraper/1.0",
    retries: int = 2,
) -> ScrapeResult:
    url_key = validate_url(url)
    observed_at = datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")

    for attempt in range(retries + 1):
        try:
            payload = _fetch_product(url_key, timeout_seconds, user_agent)
            return parse_product_payload(
                payload,
                requested_url=url,
                final_url=url,
                expected_url_key=url_key,
                observed_at=observed_at,
            )
        except HTTPError as error:
            if error.code in RETRYABLE_STATUSES and attempt < retries:
                _retry_delay(attempt, error.headers.get("Retry-After"))
                continue
            raise ScrapeError(
                "http_error",
                f"Sportland returned HTTP {error.code}.",
                retryable=error.code in RETRYABLE_STATUSES,
            ) from error
        except (TimeoutError, URLError) as error:
            if attempt < retries:
                _retry_delay(attempt)
                continue
            raise ScrapeError(
                "network_error",
                "The Sportland product API could not be reached.",
                retryable=True,
            ) from error

    raise ScrapeError("network_error", "The Sportland product API could not be reached.", retryable=True)


def parse_product_payload(
    payload: dict[str, Any],
    *,
    requested_url: str,
    final_url: str,
    expected_url_key: str,
    observed_at: str,
) -> ScrapeResult:
    validate_url(final_url)

    if payload.get("errors"):
        raise ScrapeError("product_data_missing", "Sportland returned an invalid product response.")

    data = payload.get("data")
    products = data.get("products", {}) if isinstance(data, dict) else {}
    items = products.get("items") if isinstance(products, dict) else None
    if not isinstance(items, list):
        raise ScrapeError("product_data_missing", "The Sportland product data could not be found.")
    if not items:
        return ScrapeResult(
            requested_url=requested_url,
            final_url=final_url,
            observed_at=observed_at,
            availability="unavailable",
            current_price=None,
            original_price=None,
            currency=None,
            sku=None,
            sizes=[],
        )

    product = items[0]
    if not isinstance(product, dict) or product.get("url_key") != expected_url_key:
        raise ScrapeError("product_mismatch", "Sportland returned a different product.")

    parent_in_stock = product.get("stock_status") == "IN_STOCK"
    sizes = _extract_sizes(product)
    if parent_in_stock and not sizes:
        raise ScrapeError("sizes_missing", "The available Sportland product has no usable size inventory.")
    availability = "available" if parent_in_stock and any(size["in_stock"] for size in sizes) else "unavailable"
    current_price, original_price, currency = _extract_prices(product)

    if availability == "available" and (current_price is None or currency is None):
        raise ScrapeError("price_missing", "The available Sportland product has no usable price.")

    return ScrapeResult(
        requested_url=requested_url,
        final_url=final_url,
        observed_at=observed_at,
        availability=availability,
        current_price=current_price if availability == "available" else None,
        original_price=original_price if availability == "available" else None,
        currency=currency if availability == "available" else None,
        sku=_optional_string(product.get("sku")),
        sizes=sizes,
    )


def validate_url(url: str) -> str:
    parsed = urlparse(url)
    try:
        port = parsed.port
    except ValueError as error:
        raise ScrapeError("url_not_allowed", "Only HTTPS Sportland product URLs may be scraped.") from error
    if (
        parsed.scheme != "https"
        or parsed.hostname not in ALLOWED_HOSTS
        or port not in {None, 443}
        or parsed.username is not None
        or parsed.password is not None
    ):
        raise ScrapeError("url_not_allowed", "Only HTTPS Sportland product URLs may be scraped.")

    match = re.fullmatch(r"/product/([a-z0-9][a-z0-9_-]*)/?", parsed.path)
    if match is None:
        raise ScrapeError("url_not_allowed", "The URL is not a supported Sportland product page.")
    return match.group(1)


def _fetch_product(url_key: str, timeout_seconds: float, user_agent: str) -> dict[str, Any]:
    body = json.dumps({"query": PRODUCT_QUERY, "variables": {"urlKey": url_key}}).encode()
    request = Request(
        GRAPHQL_ENDPOINT,
        data=body,
        headers={
            "User-Agent": user_agent,
            "Accept": "application/json",
            "Content-Type": "application/json",
            "Store": "lat",
        },
        method="POST",
    )
    opener = build_opener(_SafeGraphqlRedirectHandler())
    with opener.open(request, timeout=timeout_seconds) as response:
        response_body = response.read(MAX_RESPONSE_BYTES + 1)
        if len(response_body) > MAX_RESPONSE_BYTES:
            raise ScrapeError("response_too_large", "The Sportland response exceeded the size limit.")
        try:
            decoded = json.loads(response_body)
        except (UnicodeDecodeError, json.JSONDecodeError) as error:
            raise ScrapeError("invalid_response", "Sportland returned invalid product data.") from error
        if not isinstance(decoded, dict):
            raise ScrapeError("invalid_response", "Sportland returned invalid product data.")
        return decoded


def _extract_sizes(product: dict[str, Any]) -> list[dict[str, Any]]:
    option_labels: dict[str, str] = {}
    for option in product.get("configurable_options") or []:
        if not isinstance(option, dict) or option.get("attribute_code") != "footwear_size":
            continue
        for value in option.get("values") or []:
            if not isinstance(value, dict):
                continue
            label = _size_label(value.get("label"))
            value_index = _optional_string(value.get("value_index"))
            if label is not None and value_index is not None:
                option_labels[value_index] = label

    stock_by_size = {label: False for label in option_labels.values()}
    for variant in product.get("variants") or []:
        variant_product = variant.get("product") if isinstance(variant, dict) else None
        if not isinstance(variant_product, dict):
            continue
        label = option_labels.get(str(variant_product.get("footwear_size")))
        if label is not None and variant_product.get("stock_status") == "IN_STOCK":
            stock_by_size[label] = True

    return [
        {"eu_size": label, "in_stock": in_stock}
        for label, in_stock in sorted(stock_by_size.items(), key=lambda item: Decimal(item[0]))
    ]


def _extract_prices(product: dict[str, Any]) -> tuple[str | None, str | None, str | None]:
    price_range = product.get("price_range")
    if not isinstance(price_range, dict):
        return None, None, None

    for price_key in ("minimum_price", "maximum_price"):
        price = price_range.get(price_key)
        if not isinstance(price, dict):
            continue

        regular = price.get("regular_price")
        final = price.get("final_price")
        regular = regular if isinstance(regular, dict) else {}
        final = final if isinstance(final, dict) else {}
        current_price = _money(final.get("value"))
        if current_price is None or Decimal(current_price) <= 0:
            continue

        regular_price = _money(regular.get("value"))
        currency = _optional_string(final.get("currency"))
        original_price = None
        if regular_price is not None and Decimal(regular_price) > Decimal(current_price):
            original_price = regular_price
        return current_price, original_price, currency

    return None, None, None


def _size_label(value: Any) -> str | None:
    try:
        decimal = Decimal(str(value).replace(",", "."))
    except (InvalidOperation, ValueError):
        return None
    if decimal <= 0:
        return None
    rendered = format(decimal, "f")
    return rendered.rstrip("0").rstrip(".") if "." in rendered else rendered


def _money(value: Any) -> str | None:
    if value in {None, ""}:
        return None
    try:
        decimal = Decimal(str(value).replace(",", "."))
    except InvalidOperation:
        return None
    if decimal < 0:
        return None
    return format(decimal.quantize(Decimal("0.01")), "f")


def _optional_string(value: Any) -> str | None:
    return str(value) if value not in {None, ""} else None


def _retry_delay(attempt: int, retry_after: str | None = None) -> None:
    try:
        seconds = min(10.0, max(0.0, float(retry_after))) if retry_after is not None else 2**attempt
    except ValueError:
        seconds = 2**attempt
    time.sleep(seconds)
