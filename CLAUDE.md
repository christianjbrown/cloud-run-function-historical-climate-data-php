# CLAUDE.md

Guidance for working in this repository. Match the existing conventions exactly — this codebase is
small, uniform, and highly opinionated, so new code should be indistinguishable from what's here (and
from its sibling `php-gcp-function-*` repos).

## What this is

A deployable **Google Cloud Run function** (PHP 8.5+, `php85` runtime) that returns per-day or per-hour
min/max temperature and humidity — inside from SmartThings, outside from the Met Office — over a
lookback window. It is an **application, not a library**: `run()` in `index.php` is the composition
root that wires the sibling `christianjbrown/*` packages behind one HTTP entry point.

It consumes the private `dev-main` packages `php-gcp-function-lib` (the HTTP envelope/gating/caching
framework), `php-christianbrown-database-orm` (the shared Doctrine ORM — entities, `EntityManagerFactory`,
and the `ClimateHistoryReader` that owns the aggregation SQL), and `php-user-friendly-exception-lib`,
plus `php-code-quality-scripts` (dev). The **read/aggregation logic lives in the shared ORM package**,
not here — this function only parses the path, drives the reader against both tables, and merges.

## The endpoint

`GET /{route}`, where `{route}` is a curated whitelist: `hourly-day`, `hourly-1-month`, `daily-1-month`,
`daily-3-month`, `daily-6-month`, `daily-12-month` (hourly is capped at a day/month — longer hourly
windows return too many buckets). Buckets are **UTC**. The window is `[now - lookback, now)`. Returns a `data[]` list ordered
earliest first; a bucket present on only one source keeps `null` for the other side's four fields;
values are rounded to 2 decimals. Responses are edge-cached for an hour.

## Commands

Binaries install into `bin/` (Composer `bin-dir`). Run `composer install` first (needs SSH /
`COMPOSER_AUTH` for the private packages). This app **commits `composer.lock`**.

| Task | Command |
| --- | --- |
| Run locally | `composer start` |
| Tests + coverage | `composer test` |
| Static analysis (PHPStan level max) | `composer stan` |
| Check / fix style | `composer check-style` / `composer fix-style` |

Always `composer fix-style`, then `check-style`, then `stan`, then `test` before finishing. CI
(`.github/workflows/ci.yml`) runs the same three gates on push/PR to `main`, using the `COMPOSER_AUTH`
secret — which here **must** be able to read the private `php-christianbrown-database-orm` repo.

## Architecture

Flat `src/`, PSR-4 `ChristianBrown\HistoricalClimateData\`. `index.php` (outside the namespace, so
excluded from coverage/PHPStan/phpcs) is the composition root.

- **`RequestHandler`** — wraps `factory->create()` + `CloudFunction::run()` in one `try/catch (Throwable)`,
  returning the framework's JSON error envelope on failure (identical to the sibling functions).
- **`Config` / `ConfigTransformer`** — hold and validate `CHRISTIANBROWN_DATABASE_DSN` (via the shared
  `extractRequiredString` guard), delegating the rest of the env to the lib's `FunctionConfigTransformer`.
- **`QueryParser` / `Query`** — parse `/{daily|hourly}-{month|6-month|year}` (tolerant of any route
  prefix) into a resolution + months-back; an unmatched path throws `UserFriendlyException`
  (`ERROR_INVALID_PATH`) → JSON error envelope. Period → months is a single `match` (not sequential
  `if`s) to keep path coverage from exploding.
- **`DataProvider`** — `getData()` parses the path, computes `start = now - P{months}M` (UTC), calls the
  shared `ClimateHistoryReader` once per table (inside = `smartthings_climate`, outside =
  `met_office_weather`), and hands both result sets to the `OutputTransformer`. Table names come from
  the shared entity metadata (`getClassMetadata(...)->getTableName()`), not string literals.
- **`OutputTransformer`** — merges the inside and outside per-bucket rows by a chronologically-sortable
  key (`YYYY-MM-DD` or `YYYY-MM-DDTHH`), so a plain `sort()` of the union yields earliest-first order.
  Builds each row with `$row + side($inside) + side($outside)`, where `+` preserves the fixed field
  order. `side()` branches **once** on a missing side (not per field) to avoid path-coverage explosion.

## Conventions (follow all of these)

- `declare(strict_types=1);` on every file, immediately after `<?php`.
- **Every concrete class is `final` and implements a matching `...Interface`** in the same namespace.
- **Constants live on the interface**, not the class (env keys, error messages, the month constants).
- **No constructor property promotion**; typed `private` properties assigned in the constructor body;
  class members ordered **alphabetically**.
- **A method that does not use `$this` must be `static`** (and called via `self::`) — a stateless helper
  is static. The exception is a method that *must* stay instance to implement an interface or override a
  parent (e.g. `OutputTransformer::transform()`). php-cs-fixer already enforces the equivalent for
  closures (`static fn`).
- Import functions explicitly (`use function sprintf;`) and call them unqualified.
- **Value objects** (`Config`, `Query`): required fields are constructor args; getters `getX()`.
- **Array boundaries** carry `@param`/`@return` docblocks. Because php-cs-fixer strips a `@param` that
  only restates a native type, a method that needs to type an array param must have that array param
  **first** (the positional `FunctionComment` sniff maps the lone `@param` to the first parameter) — or
  avoid the array param entirely. The reusable bucket shape is a `@phpstan-type ClimateBucket` on
  `OutputTransformerInterface`, imported into the transformer with `@phpstan-import-type`.
- **Path-coverage discipline**: prefer array functions over `foreach`; collapse N independent ternaries
  or sequential `if`s into a single branch / `match` so xdebug path combinations don't explode.

## Testing

Strict `phpunit.xml` (`requireCoverageMetadata`, `beStrictAboutCoverageMetadata`, `failOnRisky`,
`failOnWarning`, `beStrictAboutOutputDuringTests`, path coverage).

- **Keep line, branch, method, class, AND path coverage at 100%.** Run `composer test` before finishing.
- **Every test class needs `#[CoversClass(...)]`** — and list every own-class a test exercises, or
  `beStrictAboutCoverageMetadata` marks it risky (e.g. `QueryParserTest` covers `Query` because
  `parse()` constructs it; `DataProviderTest` stubs `QueryInterface` rather than constructing a `Query`).
- **Double collaborators** via their interface: `self::createStub(...)` for return-only doubles,
  `self::createMock(...)` + `->expects(...)` for verified calls. The `ClimateHistoryReader` is doubled
  through its interface (from the shared package). A handler test that logs via `error_log()` diverts
  it to a temp file (`ini_set('error_log', ...)`) so the strict-output check stays green.

## Adding a feature

1. Add the class + its matching interface (constants on the interface). Concrete classes are `final`.
2. If it needs new wiring, extend `index.php`'s `run()`. Aggregation SQL belongs in the shared ORM
   package's reader, not here.
3. Add a matching `#[CoversClass]` test, doubling collaborators.
4. Run `composer fix-style`, then `check-style`, then `stan`, then `test` and confirm 100% coverage.
