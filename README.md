# Historical Climate Data Google Cloud Run Function

[![CI](https://github.com/christianjbrown/cloud-run-function-historical-climate-data-php/actions/workflows/ci.yml/badge.svg)](https://github.com/christianjbrown/cloud-run-function-historical-climate-data-php/actions/workflows/ci.yml)

A small [Google Cloud Run function](https://cloud.google.com/run) (PHP) that returns historical **min/max temperature and humidity** — inside (from [SmartThings](https://www.smartthings.com/)) and outside (from the [Met Office](https://datahub.metoffice.gov.uk/)) — aggregated per day or per hour over a lookback window, as a single JSON payload.

The route you request picks a resolution and window (e.g. `/daily-3-month` for the last three months by day). For that window it runs one grouped `MIN`/`MAX` query per source table over the shared climate-history database, buckets the readings into **UTC** days (or hours), merges the inside and outside sides into one row per bucket, and returns them ordered earliest first. The heavy lifting — the aggregation SQL — lives in the shared `christianjbrown/christianbrown-database-orm` package's `ClimateHistoryReader`; this function parses the path, drives the reader against both tables, and merges the results.



## :heavy_check_mark: Prerequisites

- [Git](https://git-scm.com/)
- [PHP](https://www.php.net/) 8.5 or higher (8.x)
- [Composer](https://getcomposer.org/)
- A MySQL database reachable by the function, holding the shared climate-history tables (`smartthings_climate` and `met_office_weather`) that the sibling functions append to
- The `christianjbrown/*` package repositories this function depends on — all public GitHub repos, so Composer fetches them with no authentication

:bulb: If you're on macOS and have [Homebrew](https://brew.sh/), PHP and Composer will install with `brew install composer`.



## :building_construction: Installation

```bash
git clone git@github.com:christianjbrown/cloud-run-function-historical-climate-data-php.git
cd cloud-run-function-historical-climate-data
composer install
```



## :gear: Configuration

Configuration is read entirely from environment variables.

| Variable | Required | Description |
| --- | --- | --- |
| `CHRISTIANBROWN_DATABASE_DSN` | ✅ | Doctrine DSN for the MySQL database holding the shared climate-history tables. |
| `K_REVISION` | ✅ | Set automatically by the Cloud Run runtime; only needs setting yourself when running locally. |
| `REQUIRED_HEADER_KEY` | — | If set (with `REQUIRED_HEADER_VALUE`), requests must send this header to be served. |
| `REQUIRED_HEADER_VALUE` | — | Expected value for `REQUIRED_HEADER_KEY`. |
| `REQUIRED_ORIGIN` | — | Restricts responses to this CORS origin. |
| `USE_CACHE_TTL` | — | Seconds a fresh response may be cached (`Cache-Control`). |
| `USE_CACHE_BUT_REQUEST_TTL` | — | Seconds a cached response may be served while revalidating. |
| `USE_CACHE_IF_ERROR_TTL` | — | Seconds a cached response may be served if the origin errors. |
| `DEBUG` | — | Set to `true` for verbose error output. |

For local development, put these in a `.local.env` file in the project root (git-ignored). `composer start` exports it automatically:

```env
CHRISTIANBROWN_DATABASE_DSN=mysql://user:password@localhost/schema?unix_socket=/tmp/cloudsql/project:region:instance&driver=pdo_mysql
K_REVISION=local
```

Locally, the DSN's `unix_socket` typically points at a running
[Cloud SQL Auth Proxy](https://cloud.google.com/sql/docs/mysql/connect-auth-proxy) socket; in Cloud
Run the socket is `/cloudsql/<instance connection name>`, mounted by the deploy's
`--set-cloudsql-instances`.



## :computer: Usage

### Run locally

```bash
composer start
```

This serves the function at `http://localhost:8080` (override with `PORT`). Send it a request at one of the curated routes:

```bash
curl http://localhost:8080/daily-3-month
```

The `{route}` path segment is a whitelist of `{resolution}-{lookback}`. Hourly is capped at a day/month — longer hourly windows would return too many buckets — so the longer windows are daily only:

| Route | Resolution | Window |
| --- | --- | --- |
| `/hourly-day` | hourly | last day |
| `/hourly-1-month` | hourly | last month |
| `/daily-1-month` | daily | last month |
| `/daily-3-month` | daily | last 3 months |
| `/daily-6-month` | daily | last 6 months |
| `/daily-12-month` | daily | last 12 months |

The window runs from that period before *now* up to now. Buckets are **UTC** (`hour` 0 = 00:00–00:59 UTC), and responses are cached for an hour at the edge.

### Response

The payload is the shared JSON success envelope; the function-specific `data` array holds one entry per time bucket, ordered by date (then hour) earliest first:

```json
{
    "data": [
        {
            "date": "2026-07-19",
            "insideMaxTemp": 24.1,
            "insideMinTemp": 19.3,
            "insideMinHumidity": 41.2,
            "insideMaxHumidity": 58.7,
            "outsideMaxTemp": 22.6,
            "outsideMinTemp": 11.8,
            "outsideMinHumidity": 44,
            "outsideMaxHumidity": 89
        },
        {
            "date": "2026-07-20",
            "insideMaxTemp": 25,
            "insideMinTemp": 20.1,
            "insideMinHumidity": 39.5,
            "insideMaxHumidity": 55,
            "outsideMaxTemp": null,
            "outsideMinTemp": null,
            "outsideMinHumidity": null,
            "outsideMaxHumidity": null
        }
    ],
    "success": true,
    "timestamp_unix": 1784571588,
    "timestamp_iso8601": "2026-07-20T18:19:48+00:00",
    "version": "get-historical-climate-data-00007-abc"
}
```

- `data` — the time buckets over the lookback window, ordered earliest first. Omitted entirely when no data falls in the window.
- `date` — the bucket date (UTC), as `YYYY-MM-DD`. Always present on a bucket.
- `hour` — the bucket hour (UTC, `0`–`23`). Present only on the **hourly** resolutions.
- `insideMaxTemp` / `insideMinTemp` — highest/lowest inside (SmartThings) temperature in the bucket, in °C.
- `insideMinHumidity` / `insideMaxHumidity` — lowest/highest inside relative humidity in the bucket, as a percentage.
- `outsideMaxTemp` / `outsideMinTemp` — highest/lowest outside (Met Office) temperature in the bucket, in °C.
- `outsideMinHumidity` / `outsideMaxHumidity` — lowest/highest outside relative humidity in the bucket, as a percentage.
- The eight inside/outside min/max fields are always present, but a bucket that only one source reported in keeps `null` for the other side's four fields. All values are rounded to two decimals.
- `success`, `timestamp_unix`, `timestamp_iso8601`, `version` — the shared envelope fields every function returns (`version` is the Cloud Run revision that produced the response).



## :test_tube: Tests & code style

```bash
composer test              # PHPUnit with coverage, then opens the HTML report
composer check-style       # PHPCS across src/ and tests/
composer check-style-diff  # PHPCS on changed files only
composer fix-style         # auto-fix style in src/ and tests/
composer fix-style-diff    # auto-fix changed files only
```



## :books: API documentation

The committed `openapi.yaml` is generated from the `#[OA\...]` attributes in `src/`
(`composer openapi:generate`). The success response composes the shared `SuccessEnvelope` (from
`cloud-run-function-lib`) with this function's `data` array of `ClimateHistoryBucket`s via `allOf`, and
`tests/ContractTest.php` validates the function's real responses against the spec so the contract
cannot silently drift from the code. Dev-only [Redoc](https://redocly.com/redoc) tooling
(`@redocly/cli`) renders and lints it — it is separate from the PHP runtime and excluded from both git
and the GCP deploy, so it never affects the deployed function.

```bash
npm install            # one-time: installs the docs tooling (Node/npm)
npm run docs:preview   # live browser preview of openapi.yaml (local server)
npm run docs:build     # write a shareable static openapi.html (git-ignored build artifact)
npm run docs:lint      # lint openapi.yaml
```



## :rocket: CI & deployment

- **`.github/workflows/ci.yml`** runs on pushes and pull requests to `main`: `composer install`, PHPCS, PHPStan, PHPUnit, and an OpenAPI spec-drift check.
- **`.github/workflows/deploy.yml`** runs on push to `main`: deploys the Cloud Run function (`php85` runtime, `europe-west2`, function name `get-historical-climate-data`) via Workload Identity Federation, grants public (`allUsers`) invoker access on the underlying Cloud Run service, attaches the shared Cloud SQL instance (`--set-cloudsql-instances`) so the climate-history tables are reachable, smoke-tests the deployed URL, then purges the Fastly edge cache by surrogate key.

Both workflows install the `christianjbrown/*` dependencies with `composer install`. Those packages are public GitHub repositories, so no authentication is required to fetch them; the workflows still pass a `COMPOSER_AUTH` repository secret — a Composer auth JSON holding a GitHub token — only to raise GitHub's API rate limit for the install:

```json
{"github-oauth":{"github.com":"your-github-token"}}
```

The database DSN and the required-header value are supplied at deploy time from Google Secret Manager (see `deploy.yml`). The runtime service account needs `roles/cloudsql.client` on the project that owns the shared database.



## :package: Architecture

The entry point is `run()` in [`index.php`](index.php), which wires the pieces together:

- **`ConfigTransformer`** reads the environment into a `Config` (the database DSN + request/caching config), delegating the request-gating and caching env to the lib's `FunctionConfigTransformer`.
- **`EntityManagerFactory`** / **`ClimateHistoryReader`** (from [`christianjbrown/christianbrown-database-orm`](https://github.com/christianjbrown/christianbrown-database-orm-php)) build a Doctrine entity manager over the DSN and run the grouped `MIN`/`MAX` aggregation query per table; the inside/outside table names come from the shared entity metadata, not string literals.
- **`QueryParser`** / **`Query`** parse the request path into a resolution (`daily`/`hourly`) and lookback window; an unrecognised route throws a `UserFriendlyException` that becomes the JSON error envelope.
- **`DataProvider`** derives the `[now - lookback, now)` UTC window, calls the reader once for the inside (`smartthings_climate`) table and once for the outside (`met_office_weather`) table, and hands both result sets to the `OutputTransformer`.
- **`OutputTransformer`** merges the two sides by a chronologically-sortable bucket key so a plain sort yields earliest-first order, rounds every value to two decimals, and shapes the `data` array (a bucket present on one side only keeps `null` for the other side's fields).
- **`RequestHandler`** wraps the factory wiring and `CloudRunFunction::run()` in one `try/catch`, returning the framework's JSON error envelope on any failure so a database problem never escapes as a bare 500.
- **`CloudRunFunction`** (from [`christianjbrown/cloud-run-function-lib`](https://github.com/christianjbrown/cloud-run-function-lib-php)) handles the HTTP request/response, header/origin gating, and caching headers.



## :page_facing_up: License

Released under the [MIT License](LICENSE).
