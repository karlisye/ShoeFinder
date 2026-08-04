from __future__ import annotations

import json
import re
import time
from datetime import datetime, timezone
from decimal import Decimal, InvalidOperation
from html.parser import HTMLParser
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.parse import urlparse
from urllib.request import HTTPRedirectHandler, Request, build_opener

from .contract import ScrapeError, ScrapeResult

ALLOWED_HOSTS = {"ballzy.eu"}
MAX_RESPONSE_BYTES = 5 * 1024 * 1024
RETRYABLE_STATUSES = {429, 500, 502, 503, 504}


class _SafeRedirectHandler(HTTPRedirectHandler):
    def redirect_request(
        self,
        req: Request,
        fp: Any,
        code: int,
        msg: str,
        headers: Any,
        newurl: str,
    ) -> Request | None:
        validate_url(newurl)
        return super().redirect_request(req, fp, code, msg, headers, newurl)


class _ProductPageParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.json_ld_scripts: list[str] = []
        self.next_scripts: list[str] = []
        self.sizes: list[dict[str, Any]] = []
        self.original_price_texts: list[str] = []
        self._script_kind: str | None = None
        self._script_parts: list[str] = []
        self._size_depth = 0
        self._size_in_stock = False
        self._size_parts: list[str] = []
        self._original_price_depth = 0
        self._original_price_parts: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        attributes = dict(attrs)

        if tag == "script":
            script_type = attributes.get("type")
            if script_type == "application/ld+json":
                self._script_kind = "json_ld"
                self._script_parts = []
            elif self._script_kind is None:
                self._script_kind = "other"
                self._script_parts = []

        if attributes.get("data-lg") in {
            "size-option-available",
            "size-option-unavailable",
        }:
            self._size_depth = 1
            self._size_in_stock = attributes["data-lg"] == "size-option-available"
            self._size_parts = []
        elif self._size_depth:
            self._size_depth += 1

        classes = (attributes.get("class") or "").split()
        if tag == "span" and "line-through" in classes:
            self._original_price_depth = 1
            self._original_price_parts = []
        elif self._original_price_depth:
            self._original_price_depth += 1

    def handle_endtag(self, tag: str) -> None:
        if tag == "script" and self._script_kind is not None:
            content = "".join(self._script_parts)
            if self._script_kind == "json_ld":
                self.json_ld_scripts.append(content)
            elif "self.__next_f.push" in content:
                self.next_scripts.append(content)
            self._script_kind = None
            self._script_parts = []

        if self._size_depth:
            self._size_depth -= 1
            if self._size_depth == 0:
                label = _extract_size_label(" ".join(self._size_parts))
                if label is not None:
                    self.sizes.append({"eu_size": label, "in_stock": self._size_in_stock})
                self._size_parts = []

        if self._original_price_depth:
            self._original_price_depth -= 1
            if self._original_price_depth == 0:
                self.original_price_texts.append(" ".join(self._original_price_parts))
                self._original_price_parts = []

    def handle_data(self, data: str) -> None:
        if self._script_kind is not None:
            self._script_parts.append(data)
        if self._size_depth:
            self._size_parts.append(data)
        if self._original_price_depth:
            self._original_price_parts.append(data)


def scrape(
    url: str,
    *,
    timeout_seconds: float = 20,
    user_agent: str = "ShoeFinderScraper/1.0",
    retries: int = 2,
) -> ScrapeResult:
    validate_url(url)
    observed_at = datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")

    for attempt in range(retries + 1):
        try:
            html, final_url = _fetch(url, timeout_seconds, user_agent)
            return parse_product_page(
                html,
                requested_url=url,
                final_url=final_url,
                observed_at=observed_at,
            )
        except HTTPError as error:
            if error.code in {404, 410}:
                return ScrapeResult(
                    requested_url=url,
                    final_url=error.geturl(),
                    observed_at=observed_at,
                    availability="unavailable",
                    current_price=None,
                    original_price=None,
                    currency=None,
                    sku=None,
                    sizes=[],
                )
            if error.code in RETRYABLE_STATUSES and attempt < retries:
                _retry_delay(attempt, error.headers.get("Retry-After"))
                continue
            raise ScrapeError(
                "http_error",
                f"Ballzy returned HTTP {error.code}.",
                retryable=error.code in RETRYABLE_STATUSES,
            ) from error
        except (TimeoutError, URLError) as error:
            if attempt < retries:
                _retry_delay(attempt)
                continue
            raise ScrapeError("network_error", "The Ballzy page could not be reached.", retryable=True) from error

    raise ScrapeError("network_error", "The Ballzy page could not be reached.", retryable=True)


def parse_product_page(
    html: str,
    *,
    requested_url: str,
    final_url: str,
    observed_at: str,
) -> ScrapeResult:
    validate_url(final_url)

    if "cf-chl-" in html or re.search(r"<title>\s*Just a moment", html, re.IGNORECASE):
        raise ScrapeError("challenge_page", "Ballzy returned an anti-bot challenge.", retryable=True)

    parser = _ProductPageParser()
    parser.feed(html)
    product = _find_product_json_ld(parser.json_ld_scripts)

    if product is None:
        raise ScrapeError("product_data_missing", "The Ballzy product data could not be found.")

    offer = _first_offer(product.get("offers"))
    availability_value = str(offer.get("availability", ""))
    currency = _optional_string(offer.get("priceCurrency"))
    current_price = _money(offer.get("price"))
    original_price = _original_price(parser, current_price)
    sizes = _deduplicate_sizes(parser.sizes)
    explicitly_unavailable = availability_value.endswith(("OutOfStock", "Discontinued", "SoldOut"))
    availability = "available" if any(size["in_stock"] for size in sizes) else "unavailable"

    if explicitly_unavailable:
        availability = "unavailable"

    if availability == "available" and (current_price is None or currency is None):
        raise ScrapeError("price_missing", "The available Ballzy product has no usable price.")

    if availability == "available" and not sizes:
        raise ScrapeError("sizes_missing", "The available Ballzy product has no usable size inventory.")

    return ScrapeResult(
        requested_url=requested_url,
        final_url=final_url,
        observed_at=observed_at,
        availability=availability,
        current_price=current_price,
        original_price=original_price,
        currency=currency,
        sku=_optional_string(product.get("sku")),
        sizes=sizes,
    )


def validate_url(url: str) -> None:
    parsed = urlparse(url)
    if parsed.scheme != "https" or parsed.hostname not in ALLOWED_HOSTS or parsed.port not in {None, 443}:
        raise ScrapeError("url_not_allowed", "Only HTTPS Ballzy product URLs may be scraped.")
    if not parsed.path.startswith(("/en/product/", "/lv/product/")):
        raise ScrapeError("url_not_allowed", "The URL is not a supported Ballzy product page.")


def _fetch(url: str, timeout_seconds: float, user_agent: str) -> tuple[str, str]:
    request = Request(url, headers={"User-Agent": user_agent, "Accept": "text/html,application/xhtml+xml"})
    opener = build_opener(_SafeRedirectHandler())
    with opener.open(request, timeout=timeout_seconds) as response:
        final_url = response.geturl()
        validate_url(final_url)
        body = response.read(MAX_RESPONSE_BYTES + 1)
        if len(body) > MAX_RESPONSE_BYTES:
            raise ScrapeError("response_too_large", "The Ballzy response exceeded the size limit.")
        charset = response.headers.get_content_charset() or "utf-8"
        return body.decode(charset, errors="replace"), final_url


def _find_product_json_ld(scripts: list[str]) -> dict[str, Any] | None:
    for script in scripts:
        try:
            value = json.loads(script)
        except json.JSONDecodeError:
            continue
        candidates = value if isinstance(value, list) else [value]
        for candidate in candidates:
            if isinstance(candidate, dict) and candidate.get("@type") == "Product":
                return candidate
            if isinstance(candidate, dict) and isinstance(candidate.get("@graph"), list):
                for graph_item in candidate["@graph"]:
                    if isinstance(graph_item, dict) and graph_item.get("@type") == "Product":
                        return graph_item
    return None


def _first_offer(value: Any) -> dict[str, Any]:
    if isinstance(value, dict):
        return value
    if isinstance(value, list):
        return next((offer for offer in value if isinstance(offer, dict)), {})
    return {}


def _original_price(parser: _ProductPageParser, current_price: str | None) -> str | None:
    for text in parser.original_price_texts:
        candidate = _money_from_text(text)
        if candidate is not None and (current_price is None or Decimal(candidate) > Decimal(current_price)):
            return candidate

    next_data = "\n".join(_decode_next_fragments(script) for script in parser.next_scripts)
    match = re.search(
        r'"regularPrice":\{"amount":\{"currency":"[A-Z]{3}","value":([0-9]+(?:\.[0-9]+)?)',
        next_data,
    )
    if match:
        candidate = _money(match.group(1))
        if candidate is not None and (current_price is None or Decimal(candidate) > Decimal(current_price)):
            return candidate
    return None


def _decode_next_fragments(script: str) -> str:
    fragments: list[str] = []
    pattern = re.compile(r'self\.__next_f\.push\(\[1,"((?:\\.|[^"\\])*)"\]\)')
    for match in pattern.finditer(script):
        try:
            fragments.append(json.loads(f'"{match.group(1)}"'))
        except json.JSONDecodeError:
            continue
    return "".join(fragments)


def _extract_size_label(text: str) -> str | None:
    matches = re.findall(r"(?<!\d)(\d{1,2}(?:[.,]\d)?)(?!\d)", text)
    if not matches:
        return None
    try:
        value = Decimal(matches[-1].replace(",", "."))
    except InvalidOperation:
        return None
    rendered = format(value, "f")
    return rendered.rstrip("0").rstrip(".") if "." in rendered else rendered


def _deduplicate_sizes(sizes: list[dict[str, Any]]) -> list[dict[str, Any]]:
    by_label: dict[str, bool] = {}
    for size in sizes:
        label = str(size["eu_size"])
        by_label[label] = by_label.get(label, False) or bool(size["in_stock"])
    return [
        {"eu_size": label, "in_stock": in_stock}
        for label, in_stock in sorted(by_label.items(), key=lambda item: Decimal(item[0]))
    ]


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


def _money_from_text(text: str) -> str | None:
    match = re.search(r"([0-9]+(?:[.,][0-9]{1,2})?)\s*€", text)
    return _money(match.group(1)) if match else None


def _optional_string(value: Any) -> str | None:
    return str(value) if value not in {None, ""} else None


def _retry_delay(attempt: int, retry_after: str | None = None) -> None:
    try:
        seconds = min(10.0, max(0.0, float(retry_after))) if retry_after is not None else 2**attempt
    except ValueError:
        seconds = 2**attempt
    time.sleep(seconds)
