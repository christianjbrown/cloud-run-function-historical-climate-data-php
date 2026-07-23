<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

interface QueryParserInterface
{
    public const string ERROR_INVALID_PATH = 'Invalid path. Expected /{daily|hourly}-{month|6-month|year}.';
    public const int MONTHS_6_MONTH = 6;
    public const int MONTHS_MONTH = 1;
    public const int MONTHS_YEAR = 12;

    /**
     * Parses a `/{daily|hourly}-{month|6-month|year}` request path into a Query.
     */
    public function parse(string $path): QueryInterface;
}
