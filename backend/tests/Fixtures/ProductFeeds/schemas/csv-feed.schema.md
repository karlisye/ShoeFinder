# Sole Market CSV contract

- Encoding: UTF-8
- Header: required
- Separator: semicolon (`;`)
- Quote: double quote (`"`)
- Decimal separator: comma (`,`)
- Boolean values: `1` and `0`
- Date format: ISO 8601 with offset

| Column | Required | Type |
| --- | --- | --- |
| `external_id` | One identity required | String |
| `sku` | One identity required | String |
| `gtin` | No | 8, 12, 13, or 14 digits |
| `style_code` | No | String |
| `variant_code` | Yes | String |
| `brand` | Yes | String |
| `title` | Yes | String |
| `colour` | Yes | String |
| `product_url` | Yes | HTTPS URL |
| `affiliate_url` | No | HTTPS URL |
| `price` | Yes | Nonnegative decimal |
| `original_price` | No | Decimal not below `price` |
| `currency` | Yes | `EUR` |
| `delivery_cost` | No | Nonnegative decimal |
| `delivery_min_days` | No | Nonnegative integer |
| `delivery_max_days` | No | Integer not below minimum |
| `delivery_note_lv` | No | UTF-8 string |
| `delivery_note_en` | No | UTF-8 string |
| `images` | No | Pipe-separated HTTPS URLs |
| `sizes` | Yes | Pipe-separated `eu_size:in_stock:price` values |
| `active` | Yes | `1` or `0` |
| `observed_at` | Yes | ISO 8601 timestamp |

At least one of `external_id` and `sku` must be present. Unknown columns are reported.
