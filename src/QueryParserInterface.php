<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

interface QueryParserInterface
{
    public const string ERROR_INVALID_PATH = 'Invalid path. Expected one of /hourly-day, /hourly-month, /daily-3-month, /daily-6-month, /daily-year.';

    /**
     * Parses a request path into a Query. Valid routes are a curated whitelist:
     * hourly is capped at a day/month (longer hourly windows return too much data).
     */
    public function parse(string $path): QueryInterface;
}
