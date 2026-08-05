from __future__ import annotations

import json
import subprocess
import sys
import unittest
from pathlib import Path

SCRAPER_ROOT = Path(__file__).resolve().parents[1]
FIXTURES = Path(__file__).parent / "fixtures"
sys.path.insert(0, str(SCRAPER_ROOT))

from shoe_scraper.contract import ScrapeError  # noqa: E402
from shoe_scraper.sportland import parse_product_payload, validate_url  # noqa: E402


class SportlandParserTest(unittest.TestCase):
    def parse(self, fixture: str, url_key: str):
        return parse_product_payload(
            json.loads((FIXTURES / fixture).read_text()),
            requested_url=f"https://sportland.lv/product/{url_key}",
            final_url=f"https://sportland.lv/product/{url_key}",
            expected_url_key=url_key,
            observed_at="2026-08-04T12:00:00Z",
        )

    def test_parses_regular_price_and_complete_size_stock(self) -> None:
        result = self.parse(
            "sportland-regular.json",
            "nike_air_force_1_07_mens_shoes_cw2288_111",
        )

        self.assertEqual("available", result.availability)
        self.assertEqual("134.99", result.current_price)
        self.assertIsNone(result.original_price)
        self.assertEqual("EUR", result.currency)
        self.assertEqual("CW2288_111", result.sku)
        self.assertEqual(
            [
                {"eu_size": "42", "in_stock": True},
                {"eu_size": "42.5", "in_stock": False},
                {"eu_size": "44", "in_stock": True},
            ],
            result.sizes,
        )

    def test_parses_sale_price(self) -> None:
        result = self.parse("sportland-sale.json", "nike_air_max_270_ah8050_005")

        self.assertEqual("104.99", result.current_price)
        self.assertEqual("174.99", result.original_price)

    def test_empty_product_result_is_authoritatively_unavailable(self) -> None:
        result = parse_product_payload(
            {"data": {"products": {"items": []}}},
            requested_url="https://sportland.lv/product/missing_shoe",
            final_url="https://sportland.lv/product/missing_shoe",
            expected_url_key="missing_shoe",
            observed_at="2026-08-04T12:00:00Z",
        )

        self.assertEqual("unavailable", result.availability)
        self.assertEqual([], result.sizes)
        self.assertIsNone(result.current_price)

    def test_rejects_errors_mismatches_and_non_product_urls(self) -> None:
        with self.assertRaisesRegex(ScrapeError, "invalid product response"):
            parse_product_payload(
                {"errors": [{"message": "Nope"}]},
                requested_url="https://sportland.lv/product/test_shoe",
                final_url="https://sportland.lv/product/test_shoe",
                expected_url_key="test_shoe",
                observed_at="2026-08-04T12:00:00Z",
            )

        for url in [
            "http://sportland.lv/product/test_shoe",
            "https://example.com/product/test_shoe",
            "https://sportland.lv/category/shoes",
            "https://sportland.lv/product/",
            "https://sportland.lv:invalid/product/test_shoe",
        ]:
            with self.subTest(url=url), self.assertRaises(ScrapeError):
                validate_url(url)

    def test_available_product_without_size_inventory_fails_closed(self) -> None:
        payload = json.loads((FIXTURES / "sportland-regular.json").read_text())
        payload["data"]["products"]["items"][0]["configurable_options"] = []

        with self.assertRaisesRegex(ScrapeError, "no usable size inventory"):
            parse_product_payload(
                payload,
                requested_url="https://sportland.lv/product/nike_air_force_1_07_mens_shoes_cw2288_111",
                final_url="https://sportland.lv/product/nike_air_force_1_07_mens_shoes_cw2288_111",
                expected_url_key="nike_air_force_1_07_mens_shoes_cw2288_111",
                observed_at="2026-08-04T12:00:00Z",
            )

    def test_cli_dispatches_sportland_requests(self) -> None:
        process = subprocess.run(
            [sys.executable, "-m", "shoe_scraper"],
            cwd=SCRAPER_ROOT,
            input=json.dumps({"adapter": "sportland", "url": "https://example.com/product/test"}),
            text=True,
            capture_output=True,
            check=False,
        )

        self.assertEqual(0, process.returncode)
        response = json.loads(process.stdout)
        self.assertFalse(response["ok"])
        self.assertEqual("url_not_allowed", response["error"]["code"])
        self.assertEqual("", process.stderr)


if __name__ == "__main__":
    unittest.main()
