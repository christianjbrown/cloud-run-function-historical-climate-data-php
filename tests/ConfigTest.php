<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData\Tests;

use ChristianBrown\GcpFunction\FunctionConfigInterface;
use ChristianBrown\HistoricalClimateData\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function test(): void
    {
        $functionConfig = self::createStub(FunctionConfigInterface::class);
        $config = new Config($functionConfig, 'test-database-dsn');
        self::assertSame($functionConfig, $config->getFunctionConfig());
        self::assertSame('test-database-dsn', $config->getDatabaseDsn());
    }
}
