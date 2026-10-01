<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData\Tests;

use ChristianBrown\HistoricalClimateData\TwoDecimalRounder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TwoDecimalRounder::class)]
final class TwoDecimalRounderTest extends TestCase
{
    public function testKeepsNull(): void
    {
        self::assertNull((new TwoDecimalRounder())->round(null));
    }

    public function testRoundsToTwoDecimals(): void
    {
        self::assertSame(24.87, (new TwoDecimalRounder())->round(24.866666));
    }
}
