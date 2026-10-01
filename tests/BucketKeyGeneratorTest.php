<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData\Tests;

use ChristianBrown\HistoricalClimateData\BucketKeyGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BucketKeyGenerator::class)]
final class BucketKeyGeneratorTest extends TestCase
{
    public function testDailyKeyIsTheDate(): void
    {
        $bucket = ['date' => '2026-07-20', 'hour' => null, 'minTemperature' => null, 'maxTemperature' => null, 'minHumidity' => null, 'maxHumidity' => null];

        self::assertSame('2026-07-20', (new BucketKeyGenerator())->generate($bucket, 'daily'));
    }

    public function testHourlyKeyIncludesThePaddedHour(): void
    {
        $bucket = ['date' => '2026-07-20', 'hour' => 9, 'minTemperature' => null, 'maxTemperature' => null, 'minHumidity' => null, 'maxHumidity' => null];

        self::assertSame('2026-07-20T09', (new BucketKeyGenerator())->generate($bucket, 'hourly'));
    }
}
