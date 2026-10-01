<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData\Tests;

use ChristianBrown\Database\ClimateHistoryReaderInterface;
use ChristianBrown\HistoricalClimateData\DataProvider;
use ChristianBrown\HistoricalClimateData\OutputTransformerInterface;
use ChristianBrown\HistoricalClimateData\QueryInterface;
use ChristianBrown\HistoricalClimateData\QueryParserInterface;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Clock\MockClock;

#[CoversClass(DataProvider::class)]
final class DataProviderTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testGetData(): void
    {
        $uri = self::createStub(UriInterface::class);
        $uri->method('getPath')
            ->willReturn('/daily-6-month');
        $request = self::createStub(ServerRequestInterface::class);
        $request->method('getUri')
            ->willReturn($uri);

        $query = self::createStub(QueryInterface::class);
        $query->method('getResolution')
            ->willReturn('daily');
        $query->method('getLookback')
            ->willReturn('P6M');

        $queryParser = self::createMock(QueryParserInterface::class);
        $queryParser->expects(self::once())
            ->method('parse')
            ->with('/daily-6-month')
            ->willReturn($query);

        $insideRows = [['date' => '2026-07-20', 'hour' => null, 'minTemperature' => 18.0, 'maxTemperature' => 24.0, 'minHumidity' => 40.0, 'maxHumidity' => 55.0]];
        $outsideRows = [['date' => '2026-07-20', 'hour' => null, 'minTemperature' => 10.0, 'maxTemperature' => 20.0, 'minHumidity' => 60.0, 'maxHumidity' => 80.0]];

        // Inside comes from the SmartThings table, outside from the Met Office table.
        $reader = self::createMock(ClimateHistoryReaderInterface::class);
        $reader->expects(self::exactly(2))
            ->method('read')
            ->with(
                self::anything(),
                'daily',
                new DateTimeImmutable('2026-01-15 12:00:00 UTC'),
                new DateTimeImmutable('2026-07-15 12:00:00 UTC')
            )
            ->willReturnCallback(static fn (string $table): array => 'smartthings_climate' === $table ? $insideRows : $outsideRows);

        $outputTransformer = self::createMock(OutputTransformerInterface::class);
        $outputTransformer->expects(self::once())
            ->method('transform')
            ->with($insideRows, $outsideRows, 'daily')
            ->willReturn(['test-output']);

        $dataProvider = new DataProvider($reader, $queryParser, $outputTransformer, new MockClock('2026-07-15 12:00:00 UTC'), 'smartthings_climate', 'met_office_weather');

        self::assertSame(['test-output'], $dataProvider->getData($request));
    }

    public function testGetDataReadsNowOnEveryCall(): void
    {
        $query = self::createStub(QueryInterface::class);
        $query->method('getResolution')
            ->willReturn('daily');
        $query->method('getLookback')
            ->willReturn('P1D');
        $queryParser = self::createStub(QueryParserInterface::class);
        $queryParser->method('parse')
            ->willReturn($query);
        $uri = self::createStub(UriInterface::class);
        $request = self::createStub(ServerRequestInterface::class);
        $request->method('getUri')
            ->willReturn($uri);

        $ends = [];
        $reader = self::createStub(ClimateHistoryReaderInterface::class);
        $reader->method('read')
            ->willReturnCallback(static function (string $table, string $resolution, DateTimeImmutable $start, DateTimeImmutable $end) use (&$ends): array {
                $ends[] = $end->format('H:i');

                return [];
            });
        $outputTransformer = self::createStub(OutputTransformerInterface::class);
        $outputTransformer->method('transform')
            ->willReturn([]);

        $clock = new MockClock('2026-07-15 12:00:00 UTC');
        $dataProvider = new DataProvider($reader, $queryParser, $outputTransformer, $clock, 'a', 'b');

        $dataProvider->getData($request);
        $clock->sleep(3600);
        $dataProvider->getData($request);

        self::assertSame(['12:00', '12:00', '13:00', '13:00'], $ends);
    }
}
