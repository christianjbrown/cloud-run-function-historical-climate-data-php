# get-historical-climate-data

A PHP 8.5+ Google Cloud Run function that returns historical **min/max temperature and humidity**,
inside (from SmartThings) and outside (from the Met Office), aggregated per day or per hour over a
lookback window.

## Endpoint

`GET /{route}`, where `{route}` is one of a curated whitelist (hourly is capped at a day/month — longer
hourly windows would return too many buckets):

| Route | Resolution | Window |
| --- | --- | --- |
| `/hourly-day` | hourly | last day |
| `/hourly-1-month` | hourly | last month |
| `/daily-1-month` | daily | last month |
| `/daily-3-month` | daily | last 3 months |
| `/daily-6-month` | daily | last 6 months |
| `/daily-12-month` | daily | last 12 months |

The window runs from that period before *now* up to now. Buckets are **UTC** (`hour` 0 = 00:00–00:59
UTC). Responses are cached for an hour at the edge.

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

A bucket present on only one source keeps `null` for the other side's four fields. Values are rounded
to two decimals. The response shape is documented by the committed `openapi.yaml` (see
[API documentation](#api-documentation)).

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
| Regenerate `openapi.yaml` from `#[OA\...]` attributes | `composer openapi:generate` |
| Preview the API docs live in a browser | `npm install` then `npm run docs:preview` |
| Build a shareable static `openapi.html` | `npm run docs:build` |
| Lint `openapi.yaml` | `npm run docs:lint` |

A local run needs `CHRISTIANBROWN_DATABASE_DSN` (a reachable MySQL DSN — e.g. the shared instance via
the Cloud SQL proxy) and `K_REVISION` in `.local.env`; see the sibling functions for the full env-var
list.

## API documentation

The HTTP contract is described by the committed [`openapi.yaml`](openapi.yaml), which is **generated**
from the `#[OA\...]` attributes in `src/` (`OpenApi.php` plus the `ClimateHistoryBucket` schema on
`OutputTransformerInterface`) — run `composer openapi:generate` to rebuild it. The success response
composes the shared `SuccessEnvelope` (from `php-gcp-function-lib`) with this function's `data` array of
`ClimateHistoryBucket`s via `allOf`. `tests/ContractTest.php` validates the function's real responses
against the spec, so the contract cannot silently drift from the code.

The `npm run docs:*` scripts are **dev-only** [Redoc](https://redocly.com/redoc) tooling
(`@redocly/cli`) for rendering/linting the spec; they are separate from the PHP runtime and excluded
from git and the GCP deploy (`node_modules/`, `package.json`, `redocly.yaml`, `openapi.html` are all in
`.gcloudignore`). Do not hand-edit `openapi.yaml` — CI regenerates it and fails on any drift.
