# Product-feed fixtures

These files test future product-feed adapters and matching rules. They do not reproduce a real retailer feed contract.

## Retailers

| Retailer | File format | Format behavior |
| --- | --- | --- |
| Sole Market | CSV | UTF-8, semicolon separator, quoted text, decimal comma |
| Urban Step | JSON | Feed metadata with nested product, delivery, image, and size data |
| Sneaker Point | JSON Lines | One complete product listing per UTF-8 line |
| Apavu nams | XML | Namespaced catalogue with nested images and sizes |

All retailer names, domains, feed layouts, external IDs, SKUs, GTINs, and affiliate values are invented. Manufacturer product names and codes are realistic test content.

## Directories

- `clean/`: first valid snapshot with 25 listings per retailer.
- `updates/`: second snapshot with price, availability, delivery, identity, and listing changes.
- `invalid/`: separate syntax and data-validation failures.
- `expected/initial-normalized.json`: format-independent representation of every clean listing.
- `expected/update-normalized.json`: normalized update records and expected reconciliation outcomes.
- `expected/validation-errors.json`: stable expected error locations and codes.
- `schemas/`: input contracts for each feed format.

## Normalized record

Every valid input record maps to:

- Retailer external ID and SKU.
- Optional GTIN and manufacturer style code.
- Manufacturer variant code.
- Official brand and product title.
- Raw retailer colour.
- Product and optional affiliate URL.
- Current and optional original price as decimal strings.
- Three-letter currency.
- Structured delivery values.
- Ordered image URLs.
- EU size availability with optional size-specific prices.
- Active state and ISO 8601 observation time.

Identifiers remain strings so leading zeroes are preserved. Unknown delivery cost remains `null`. It is never converted to zero.

## CSV conventions

Sole Market uses `;` as the separator and `,` as the decimal separator. `images` contains pipe-separated URLs. `sizes` uses:

```text
eu_size:in_stock:price|eu_size:in_stock:price
```

`in_stock` is `1` or `0`. An empty size price inherits the listing price.

## Update snapshot

Every update file covers:

- Price reduction.
- Discount removal and addition.
- Size sold out and restocked.
- New size.
- Delivery change.
- Raw title and colour changes.
- Affiliate URL change.
- Inactive listing.
- Omitted listing.
- New listing.
- Conflicting GTIN for an otherwise stable retailer identity.
- Unchanged listings.

The expected outcomes are `created`, `updated`, `unchanged`, `unavailable`, `manual_review`, and `missing`.

`missing` is report-only. A missing row must not be deleted or deactivated from one snapshot alone.

## Invalid fixtures

Invalid files cover malformed syntax, missing identity, duplicate identity, conflicting identity, invalid GTIN, invalid money, unsupported currency, invalid URL, invalid boolean, unknown fields, duplicate sizes, invalid delivery range, and invalid timestamps.

Expected errors contain:

- `file`: fixture filename.
- `record`: one-based source row or line when available.
- `field`: source field when available.
- `code`: stable machine-readable error code.

## Regeneration

Run from the project root:

```sh
docker compose exec -T backend-php php tests/Fixtures/ProductFeeds/generate.php
```

The generator uses fixed product data and timestamps. Regeneration must produce the same tracked files.

## Scope

These fixtures do not implement parsing, matching, persistence, scheduled imports, queues, or administrator controls.
