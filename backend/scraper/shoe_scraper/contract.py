from __future__ import annotations

from dataclasses import dataclass
from typing import Any


class ScrapeError(Exception):
    def __init__(self, code: str, message: str, *, retryable: bool = False) -> None:
        super().__init__(message)
        self.code = code
        self.retryable = retryable


@dataclass(frozen=True)
class ScrapeResult:
    requested_url: str
    final_url: str
    observed_at: str
    availability: str
    current_price: str | None
    original_price: str | None
    currency: str | None
    sku: str | None
    sizes: list[dict[str, Any]]

    def as_dict(self) -> dict[str, Any]:
        return {
            "requested_url": self.requested_url,
            "final_url": self.final_url,
            "observed_at": self.observed_at,
            "availability": self.availability,
            "current_price": self.current_price,
            "original_price": self.original_price,
            "currency": self.currency,
            "sku": self.sku,
            "sizes": self.sizes,
        }
