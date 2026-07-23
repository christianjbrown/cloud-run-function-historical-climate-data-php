<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData\Tests;

use ChristianBrown\HistoricalClimateData\Query;
use ChristianBrown\HistoricalClimateData\QueryParser;
use ChristianBrown\HistoricalClimateData\QueryParserInterface;
use ChristianBrown\UserFriendlyException\UserFriendlyException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Query::class)]
#[CoversClass(QueryParser::class)]
final class QueryParserTest extends TestCase
{
    #[TestWith(['/hourly-day', 'hourly', 'P1D'])]
    #[TestWith(['/hourly-1-month', 'hourly', 'P1M'])]
    #[TestWith(['/daily-1-month', 'daily', 'P1M'])]
    #[TestWith(['/daily-3-month', 'daily', 'P3M'])]
    #[TestWith(['/daily-6-month', 'daily', 'P6M'])]
    #[TestWith(['/daily-12-month', 'daily', 'P1Y'])]
    #[TestWith(['/get-historical-climate-data/daily-12-month', 'daily', 'P1Y'])]
    public function testParse(string $path, string $expectedResolution, string $expectedLookback): void
    {
        $query = (new QueryParser())->parse($path);

        self::assertSame($expectedResolution, $query->getResolution());
        self::assertSame($expectedLookback, $query->getLookback());
    }

    #[TestWith([''])]
    #[TestWith(['/foo'])]
    #[TestWith(['/daily'])]
    #[TestWith(['/daily-day'])]
    #[TestWith(['/daily-month'])]
    #[TestWith(['/daily-year'])]
    #[TestWith(['/hourly-month'])]
    #[TestWith(['/hourly-6-month'])]
    #[TestWith(['/hourly-year'])]
    public function testParseRejectsInvalidPaths(string $path): void
    {
        $this->expectException(UserFriendlyException::class);
        $this->expectExceptionMessage(QueryParserInterface::ERROR_INVALID_PATH);

        (new QueryParser())->parse($path);
    }
}
