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
    #[TestWith(['/daily-month', 'daily', 1])]
    #[TestWith(['/hourly-month', 'hourly', 1])]
    #[TestWith(['/daily-6-month', 'daily', 6])]
    #[TestWith(['/hourly-year', 'hourly', 12])]
    #[TestWith(['/get-historical-climate-data/daily-year', 'daily', 12])]
    public function testParse(string $path, string $expectedResolution, int $expectedMonthsBack): void
    {
        $query = (new QueryParser())->parse($path);

        self::assertSame($expectedResolution, $query->getResolution());
        self::assertSame($expectedMonthsBack, $query->getMonthsBack());
    }

    #[TestWith([''])]
    #[TestWith(['/foo'])]
    #[TestWith(['/daily'])]
    #[TestWith(['/daily-week'])]
    #[TestWith(['/weekly-year'])]
    #[TestWith(['/daily-yearx'])]
    public function testParseRejectsInvalidPaths(string $path): void
    {
        $this->expectException(UserFriendlyException::class);
        $this->expectExceptionMessage(QueryParserInterface::ERROR_INVALID_PATH);

        (new QueryParser())->parse($path);
    }
}
