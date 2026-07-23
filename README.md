# get-historical-climate-data

A PHP 8.5+ Google Cloud Run function that returns historical **min/max temperature and humidity**,
inside (from SmartThings) and outside (from the Met Office), aggregated per day or per hour over a
lookback window.

## Endpoint

```
GET /{daily|hourly}-{month|6-month|year}
```

e.g. `/daily-year`, `/hourly-month`, `/daily-6-month`. The window runs from that period before *now*
up to now. Buckets are **UTC** (`hour` 0 = 00:00–00:59 UTC).

Returns a `data[]` array, ordered by date (then hour) earliest first:

```jsonc
// daily
{ "date": "2026-07-22", "insideMaxTemp": 24.8, "insideMinTemp": 24.7,
  "insideMinHumidity": 55.0, "insideMaxHumidity": 55.0,
  "outsideMaxTemp": 18.8, "outsideMinTemp": 17.6,
  "outsideMinHumidity": 66.9, "outsideMaxHumidity": 71.3 }

// hourly adds "hour": 0–23
{ "date": "2026-07-22", "hour": 23, "insideMaxTemp": 24.8, ... }
```

A bucket present on only one source keeps `null` for the other side's four fields. Values are the exact
stored min/max (unrounded).

## How it works

The aggregation lives in the shared `christianjbrown/php-christianbrown-database-orm` package
(`ClimateHistoryReader`): one grouped `MIN`/`MAX` query per table (`smartthings_climate`,
`met_office_weather`), served by the covering index `(recorded_at, temperature, humidity)` as an
index-only scan. This function parses the path, runs the reader against both tables for the window, and
merges the two per-bucket results into the response. Responses are edge-cached by Fastly, so most
requests never reach the database.

## Commands

`composer install` first (needs SSH / `COMPOSER_AUTH` for the private sibling packages).

| Task | Command |
| --- | --- |
| Run locally (Functions Framework) | `composer start` |
| Tests + coverage | `composer test` |
| Static analysis (PHPStan level max) | `composer stan` |
| Check / fix style | `composer check-style` / `composer fix-style` |

A local run needs `CHRISTIANBROWN_DATABASE_DSN` (a reachable MySQL DSN — e.g. the shared instance via
the Cloud SQL proxy) and `K_REVISION` in `.local.env`; see the sibling functions for the full env-var
list.
