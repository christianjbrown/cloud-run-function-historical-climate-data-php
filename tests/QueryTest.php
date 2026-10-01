<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData\Tests;

use ChristianBrown\HistoricalClimateData\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Query::class)]
final class QueryTest extends TestCase
{
    public function testExposesResolutionAndLookback(): void
    {
        $query = new Query('hourly', 'P1D');

        self::assertSame('hourly', $query->getResolution());
        self::assertSame('P1D', $query->getLookback());
    }
}
