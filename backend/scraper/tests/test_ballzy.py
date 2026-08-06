from __future__ import annotations

import json
import subprocess
import sys
import unittest
from pathlib import Path

SCRAPER_ROOT = Path(__file__).resolve().parents[1]
FIXTURES = Path(__file__).parent / "fixtures"
sys.path.insert(0, str(SCRAPER_ROOT))

from shoe_scraper.ballzy import ScrapeError, parse_product_page, validate_url  # noqa: E402


class BallzyParserTest(unittest.TestCase):
    def parse(self, fixture: str):
        return parse_product_page(
            (FIXTURES / fixture).read_text(),
            requested_url="https://ballzy.eu/en/product/test-product",
            final_url="https://ballzy.eu/en/product/test-product",
            observed_at="2026-08-02T12:00:00Z",
        )

    def test_parses_sale_price_and_complete_size_stock(self) -> None:
        result = self.parse("available-sale.html")

        self.assertEqual("available", result.availability)
        self.assertEqual("119.00", result.current_price)
        self.assertEqual("134.00", result.original_price)
        self.assertEqual("EUR", result.currency)
        self.assertEqual("CW2288_111_8.5_CNF", result.sku)
        self.assertEqual(
            [
                {"eu_size": "42", "in_stock": True},
                {"eu_size": "42.5", "in_stock": False},
                {"eu_size": "44", "in_stock": True},
            ],
            result.sizes,
        )

    def test_parses_regular_price_without_original_price(self) -> None:
        result = self.parse("available-regular.html")

        self.assertEqual("83.30", result.current_price)
        self.assertIsNone(result.original_price)

    def test_parses_authoritative_unavailable_product(self) -> None:
        result = self.parse("unavailable.html")

        self.assertEqual("unavailable", result.availability)
        self.assertFalse(result.sizes[0]["in_stock"])

    def test_rejects_challenge_and_missing_product_pages(self) -> None:
        with self.assertRaisesRegex(ScrapeError, "anti-bot"):
            parse_product_page(
                "<title>Just a moment...</title><div id='cf-chl-widget'></div>",
                requested_url="https://ballzy.eu/en/product/test-product",
                final_url="https://ballzy.eu/en/product/test-product",
                observed_at="2026-08-02T12:00:00Z",
            )

        with self.assertRaisesRegex(ScrapeError, "product data"):
            parse_product_page(
                "<html><body>Not a product</body></html>",
                requested_url="https://ballzy.eu/en/product/test-product",
                final_url="https://ballzy.eu/en/product/test-product",
                observed_at="2026-08-02T12:00:00Z",
            )

    def test_rejects_non_ballzy_and_non_product_urls(self) -> None:
        for url in [
            "http://ballzy.eu/en/product/test-product",
            "https://example.com/en/product/test-product",
            "https://ballzy.eu/en/catalog",
        ]:
            with self.subTest(url=url), self.assertRaises(ScrapeError):
                validate_url(url)

    def test_cli_emits_versioned_json_for_invalid_requests(self) -> None:
        process = subprocess.run(
            [sys.executable, "-m", "shoe_scraper"],
            cwd=SCRAPER_ROOT,
            input=json.dumps({"adapter": "unknown", "url": "https://ballzy.eu/en/product/test"}),
            text=True,
            capture_output=True,
            check=False,
        )

        self.assertEqual(2, process.returncode)
        response = json.loads(process.stdout)
        self.assertEqual(1, response["schema_version"])
        self.assertFalse(response["ok"])
        self.assertEqual("invalid_request", response["error"]["code"])
        self.assertEqual("", process.stderr)


if __name__ == "__main__":
    unittest.main()
