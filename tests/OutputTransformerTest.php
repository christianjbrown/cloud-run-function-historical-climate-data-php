<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData\Tests;

use ChristianBrown\Database\ClimateHistoryReaderInterface;
use ChristianBrown\HistoricalClimateData\OutputTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OutputTransformer::class)]
final class OutputTransformerTest extends TestCase
{
    public function testDailyMergesBothSidesOrdersByDateAndNullsMissingSides(): void
    {
        // Deliberately unsorted, with an inside-only day (20th), an outside-only
        // day (21st) and a both-sides day (22nd).
        $inside = [
            ['date' => '2026-07-20', 'hour' => null, 'minTemperature' => 18.0, 'maxTemperature' => 24.0, 'minHumidity' => 40.0, 'maxHumidity' => 55.0],
            ['date' => '2026-07-22', 'hour' => null, 'minTemperature' => 19.0, 'maxTemperature' => 25.0, 'minHumidity' => 41.0, 'maxHumidity' => 56.0],
        ];
        $outside = [
            ['date' => '2026-07-22', 'hour' => null, 'minTemperature' => 11.0, 'maxTemperature' => 21.0, 'minHumidity' => 61.0, 'maxHumidity' => 81.0],
            ['date' => '2026-07-21', 'hour' => null, 'minTemperature' => 10.0, 'maxTemperature' => 20.0, 'minHumidity' => 60.0, 'maxHumidity' => 80.0],
        ];

        $actual = (new OutputTransformer())->transform($inside, $outside, ClimateHistoryReaderInterface::RESOLUTION_DAILY);

        self::assertSame([
            ['date' => '2026-07-20', 'insideMaxTemp' => 24.0, 'insideMinTemp' => 18.0, 'insideMinHumidity' => 40.0, 'insideMaxHumidity' => 55.0, 'outsideMaxTemp' => null, 'outsideMinTemp' => null, 'outsideMinHumidity' => null, 'outsideMaxHumidity' => null],
            ['date' => '2026-07-21', 'insideMaxTemp' => null, 'insideMinTemp' => null, 'insideMinHumidity' => null, 'insideMaxHumidity' => null, 'outsideMaxTemp' => 20.0, 'outsideMinTemp' => 10.0, 'outsideMinHumidity' => 60.0, 'outsideMaxHumidity' => 80.0],
            ['date' => '2026-07-22', 'insideMaxTemp' => 25.0, 'insideMinTemp' => 19.0, 'insideMinHumidity' => 41.0, 'insideMaxHumidity' => 56.0, 'outsideMaxTemp' => 21.0, 'outsideMinTemp' => 11.0, 'outsideMinHumidity' => 61.0, 'outsideMaxHumidity' => 81.0],
        ], $actual);
    }

    public function testHourlyIncludesHourAndOrdersWithinTheDay(): void
    {
        $inside = [
            ['date' => '2026-07-20', 'hour' => 14, 'minTemperature' => 20.0, 'maxTemperature' => 22.0, 'minHumidity' => 45.0, 'maxHumidity' => 48.0],
        ];
        $outside = [
            ['date' => '2026-07-20', 'hour' => 9, 'minTemperature' => 12.0, 'maxTemperature' => 14.0, 'minHumidity' => 70.0, 'maxHumidity' => 75.0],
        ];

        $actual = (new OutputTransformer())->transform($inside, $outside, ClimateHistoryReaderInterface::RESOLUTION_HOURLY);

        self::assertSame([
            ['date' => '2026-07-20', 'hour' => 9, 'insideMaxTemp' => null, 'insideMinTemp' => null, 'insideMinHumidity' => null, 'insideMaxHumidity' => null, 'outsideMaxTemp' => 14.0, 'outsideMinTemp' => 12.0, 'outsideMinHumidity' => 70.0, 'outsideMaxHumidity' => 75.0],
            ['date' => '2026-07-20', 'hour' => 14, 'insideMaxTemp' => 22.0, 'insideMinTemp' => 20.0, 'insideMinHumidity' => 45.0, 'insideMaxHumidity' => 48.0, 'outsideMaxTemp' => null, 'outsideMinTemp' => null, 'outsideMinHumidity' => null, 'outsideMaxHumidity' => null],
        ], $actual);
    }
}
