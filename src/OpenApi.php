<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

use ChristianBrown\CloudRunFunction\ResponseInterface;
use OpenApi\Attributes as OA;

/**
 * Inert OpenAPI spec-holder.
 *
 * This class carries no runtime behaviour and is never instantiated: it exists
 * only so `zircote/swagger-php` can scan its `#[OA\...]` attributes and emit the
 * committed `openapi.yaml`. Keeping the top-level `#[OA\Info]`/`#[OA\Server]` and
 * the `#[OA\Get]` operation here means the HTTP contract is generated from the same
 * typed code that produces the responses, so it cannot silently drift. The success
 * response composes the shared `SuccessEnvelope` component (from `cloud-run-function-lib`)
 * with this function's `data` array of `ClimateHistoryBucket`s via `allOf` — re-narrowing
 * only the envelope's generic `data` placeholder — and the error responses reference the
 * shared `ErrorEnvelope` component directly. It has no executable lines and is excluded
 * from coverage in `phpunit.xml`, like a config file.
 */
#[OA\Info(
    version: '1.0.0',
    description: 'Returns per-day or per-hour min/max temperature and humidity — inside from SmartThings, outside from the Met Office — over a lookback window, as a single JSON envelope whose data is an array of time buckets ordered earliest first.',
    title: 'Historical Climate Data Cloud Run function',
)]
#[OA\Server(url: '/')]
#[OA\Get(
    path: '/{route}',
    operationId: 'getClimateHistory',
    summary: 'Get historical inside/outside climate for a resolution and lookback window.',
    description: 'Returns per-bucket min/max temperature and humidity over a lookback window. The `{route}` segment is a curated whitelist of `{resolution}-{lookback}`; hourly is capped at a day/month because longer hourly windows return too many buckets.',
    parameters: [
        new OA\PathParameter(
            name: 'route',
            description: 'The resolution and lookback window. Hourly is capped at a day or a month.',
            required: true,
            schema: new OA\Schema(
                type: 'string',
                enum: ['hourly-day', 'hourly-1-month', 'daily-1-month', 'daily-3-month', 'daily-6-month', 'daily-12-month'],
            ),
        ),
    ],
    responses: [
        new OA\Response(
            response: ResponseInterface::STATUS_OK,
            description: 'The per-bucket climate history for the requested resolution and window, ordered earliest first.',
            headers: [
                new OA\Header(header: ResponseInterface::HEADER_KEY_ALLOW_METHODS, description: 'Allowed CORS methods.', schema: new OA\Schema(type: 'string')),
                new OA\Header(header: ResponseInterface::HEADER_KEY_ALLOW_ORIGIN, description: 'Configured allowed origin (present when a required origin is configured).', schema: new OA\Schema(type: 'string')),
                new OA\Header(header: ResponseInterface::HEADER_KEY_CACHE_CONTROL, description: 'CDN/browser cache directives (present when cache TTLs are configured).', schema: new OA\Schema(type: 'string')),
                new OA\Header(header: ResponseInterface::HEADER_KEY_SURROGATE_CONTROL, description: 'Surrogate cache directives (present when cache TTLs are configured).', schema: new OA\Schema(type: 'string')),
                new OA\Header(header: ResponseInterface::HEADER_KEY_VARY, description: 'Vary list (present when a required origin is configured).', schema: new OA\Schema(type: 'string')),
            ],
            content: new OA\JsonContent(
                allOf: [
                    new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                    new OA\Schema(
                        properties: [
                            new OA\Property(
                                property: ResponseInterface::RESPONSE_API_KEY_DATA,
                                description: 'The time buckets over the lookback window, ordered earliest first. Empty (and so the key is omitted) when no data falls in the window.',
                                type: 'array',
                                items: new OA\Items(ref: '#/components/schemas/ClimateHistoryBucket'),
                            ),
                        ],
                    ),
                ],
                example: [
                    ResponseInterface::RESPONSE_API_KEY_DATA => [
                        [
                            'date' => '2026-07-19',
                            'insideMaxTemp' => 24.1,
                            'insideMinTemp' => 19.3,
                            'insideMinHumidity' => 41.2,
                            'insideMaxHumidity' => 58.7,
                            'outsideMaxTemp' => 22.6,
                            'outsideMinTemp' => 11.8,
                            'outsideMinHumidity' => 44.0,
                            'outsideMaxHumidity' => 89.0,
                        ],
                        [
                            'date' => '2026-07-20',
                            'insideMaxTemp' => 25.0,
                            'insideMinTemp' => 20.1,
                            'insideMinHumidity' => 39.5,
                            'insideMaxHumidity' => 55.0,
                            'outsideMaxTemp' => null,
                            'outsideMinTemp' => null,
                            'outsideMinHumidity' => null,
                            'outsideMaxHumidity' => null,
                        ],
                    ],
                    ResponseInterface::RESPONSE_API_KEY_SUCCESS => true,
                    ResponseInterface::RESPONSE_API_KEY_TIMESTAMP_ISO8601 => '2026-07-20T18:19:48+00:00',
                    ResponseInterface::RESPONSE_API_KEY_TIMESTAMP_UNIX => 1784571588,
                    ResponseInterface::RESPONSE_API_KEY_VERSION => 'get-historical-climate-data-00007-abc',
                ],
            ),
        ),
        new OA\Response(
            response: ResponseInterface::STATUS_BAD_REQUEST,
            description: 'The path was not a recognised route. The error names the routes that are.',
            content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'),
        ),
        new OA\Response(
            response: ResponseInterface::STATUS_UNAUTHORIZED,
            description: 'The request failed header authorization.',
            content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'),
        ),
        new OA\Response(
            response: ResponseInterface::STATUS_INTERNAL_SERVER_ERROR,
            description: 'The database could not be reached, or an unhandled error occurred.',
            content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'),
        ),
    ],
)]
final class OpenApi
{
}
