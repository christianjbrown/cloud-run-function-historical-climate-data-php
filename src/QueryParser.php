<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

use ChristianBrown\UserFriendlyException\UserFriendlyException;

use function preg_match;

final class QueryParser implements QueryParserInterface
{
    public function parse(string $path): QueryInterface
    {
        // Match the trailing `{resolution}-{period}` segment, tolerant of any
        // route prefix Fastly/Cloud Run may leave in front of it.
        if (1 !== preg_match('#(?:^|/)(daily|hourly)-(month|6-month|year)$#', $path, $matches)) {
            throw new UserFriendlyException(self::ERROR_INVALID_PATH);
        }

        // A single match (not sequential ifs) so each period is one path — the
        // regex guarantees the value is one of the three, so `month` is the default.
        $monthsBack = match ($matches[2]) {
            'year' => self::MONTHS_YEAR,
            '6-month' => self::MONTHS_6_MONTH,
            default => self::MONTHS_MONTH,
        };

        return new Query($matches[1], $monthsBack);
    }
}
