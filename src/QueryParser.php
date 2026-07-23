<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

use ChristianBrown\Database\ClimateHistoryReaderInterface;
use ChristianBrown\UserFriendlyException\UserFriendlyException;

use function preg_match;

final class QueryParser implements QueryParserInterface
{
    public function parse(string $path): QueryInterface
    {
        // Grab the trailing route segment, tolerant of any route prefix
        // Fastly/Cloud Run may leave in front of it.
        if (1 !== preg_match('#(?:^|/)([a-z0-9-]+)$#', $path, $matches)) {
            throw new UserFriendlyException(self::ERROR_INVALID_PATH);
        }

        return self::resolveRoute($matches[1]);
    }

    /**
     * Curated whitelist of {resolution}-{lookback}. Hourly is capped at a
     * day/month; the longer windows are daily only.
     */
    private static function resolveRoute(string $route): QueryInterface
    {
        return match ($route) {
            'hourly-day' => new Query(ClimateHistoryReaderInterface::RESOLUTION_HOURLY, 'P1D'),
            'hourly-1-month' => new Query(ClimateHistoryReaderInterface::RESOLUTION_HOURLY, 'P1M'),
            'daily-1-month' => new Query(ClimateHistoryReaderInterface::RESOLUTION_DAILY, 'P1M'),
            'daily-3-month' => new Query(ClimateHistoryReaderInterface::RESOLUTION_DAILY, 'P3M'),
            'daily-6-month' => new Query(ClimateHistoryReaderInterface::RESOLUTION_DAILY, 'P6M'),
            'daily-12-month' => new Query(ClimateHistoryReaderInterface::RESOLUTION_DAILY, 'P1Y'),
            default => throw new UserFriendlyException(self::ERROR_INVALID_PATH),
        };
    }
}
