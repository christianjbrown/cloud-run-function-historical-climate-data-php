<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData\Tests;

use ChristianBrown\CloudRunFunction\BadRequestException;
use ChristianBrown\Database\ClimateHistoryReaderInterface;
use ChristianBrown\HistoricalClimateData\Query;
use ChristianBrown\HistoricalClimateData\QueryParser;
use ChristianBrown\HistoricalClimateData\QueryParserInterface;
use ChristianBrown\HistoricalClimateData\RouteRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryParser::class)]
#[UsesClass(Query::class)]
#[UsesClass(RouteRegistry::class)]
final class QueryParserTest extends TestCase
{
    #[TestWith(['/hourly-day', 'hourly', 'P1D'])]
    #[TestWith(['/daily-12-month', 'daily', 'P1Y'])]
    #[TestWith(['/get-historical-climate-data/daily-12-month', 'daily', 'P1Y'])]
    public function testParse(string $path, string $expectedResolution, string $expectedLookback): void
    {
        $query = $this->createParser()->parse($path);

        self::assertSame($expectedResolution, $query->getResolution());
        self::assertSame($expectedLookback, $query->getLookback());
    }

    #[TestWith([''])]
    #[TestWith(['/foo'])]
    #[TestWith(['/daily'])]
    #[TestWith(['/hourly-6-month'])]
    public function testParseRejectsInvalidPaths(string $path): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage(QueryParserInterface::ERROR_INVALID_PATH);

        $this->createParser()->parse($path);
    }

    private function createParser(): QueryParser
    {
        return new QueryParser(new RouteRegistry([
            'hourly-day' => new Query(ClimateHistoryReaderInterface::RESOLUTION_HOURLY, 'P1D'),
            'daily-12-month' => new Query(ClimateHistoryReaderInterface::RESOLUTION_DAILY, 'P1Y'),
        ]));
    }
}
