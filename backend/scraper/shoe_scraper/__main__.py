from __future__ import annotations

import json
import sys
from typing import Any

from . import SCHEMA_VERSION
from .ballzy import ScrapeError, scrape


def main() -> int:
    try:
        request = _read_request()
    except (json.JSONDecodeError, ValueError) as error:
        _write({
            "schema_version": SCHEMA_VERSION,
            "ok": False,
            "error": {"code": "invalid_request", "message": str(error), "retryable": False},
        })
        return 2

    try:
        result = scrape(
            request["url"],
            timeout_seconds=float(request.get("timeout_seconds", 20)),
            user_agent=str(request.get("user_agent", "ShoeFinderScraper/1.0")),
        )
    except ScrapeError as error:
        _write({
            "schema_version": SCHEMA_VERSION,
            "ok": False,
            "request_id": request.get("request_id"),
            "error": {"code": error.code, "message": str(error), "retryable": error.retryable},
        })
        return 0

    _write({
        "schema_version": SCHEMA_VERSION,
        "ok": True,
        "request_id": request.get("request_id"),
        **result.as_dict(),
    })
    return 0


def _read_request() -> dict[str, Any]:
    request = json.loads(sys.stdin.read())
    if not isinstance(request, dict):
        raise ValueError("The scraper request must be a JSON object.")
    if request.get("adapter") != "ballzy":
        raise ValueError("The scraper adapter is not supported.")
    if not isinstance(request.get("url"), str) or not request["url"]:
        raise ValueError("The scraper request requires a URL.")
    return request


def _write(value: dict[str, Any]) -> None:
    sys.stdout.write(json.dumps(value, separators=(",", ":")) + "\n")


if __name__ == "__main__":
    raise SystemExit(main())
