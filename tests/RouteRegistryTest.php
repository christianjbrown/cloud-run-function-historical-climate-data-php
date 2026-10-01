<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData\Tests;

use ChristianBrown\HistoricalClimateData\Query;
use ChristianBrown\HistoricalClimateData\RouteRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteRegistry::class)]
#[UsesClass(Query::class)]
final class RouteRegistryTest extends TestCase
{
    public function testFindReturnsNullForAnUnknownRoute(): void
    {
        self::assertNull((new RouteRegistry([]))->find('nope'));
    }

    public function testFindReturnsTheDefinedQuery(): void
    {
        $query = new Query('daily', 'P1M');

        self::assertSame($query, (new RouteRegistry(['daily-1-month' => $query]))->find('daily-1-month'));
    }
}
