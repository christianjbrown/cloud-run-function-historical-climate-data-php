<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData\Tests;

use ChristianBrown\CloudRunFunction\FunctionConfigInterface;
use ChristianBrown\CloudRunFunction\FunctionConfigTransformerInterface;
use ChristianBrown\HistoricalClimateData\Config;
use ChristianBrown\HistoricalClimateData\ConfigTransformer;
use ChristianBrown\HistoricalClimateData\ConfigTransformerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sprintf;

#[CoversClass(Config::class)]
#[CoversClass(ConfigTransformer::class)]
final class ConfigTransformerTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testTransform(): void
    {
        $env = [ConfigTransformerInterface::ENV_DATABASE_DSN => 'test-database-dsn'];

        $functionConfig = self::createStub(FunctionConfigInterface::class);

        $functionConfigTransformer = self::createMock(FunctionConfigTransformerInterface::class);
        $functionConfigTransformer->expects(self::once())
            ->method('transform')
            ->with($env)
            ->willReturn($functionConfig);

        $transformer = new ConfigTransformer($functionConfigTransformer);
        $actual = $transformer->transform($env);

        self::assertSame('test-database-dsn', $actual->getDatabaseDsn());
        self::assertSame($functionConfig, $actual->getFunctionConfig());
    }

    /**
     * @param mixed[] $env
     *
     * @throws Exception
     */
    #[TestWith([[]])]
    #[TestWith([[ConfigTransformerInterface::ENV_DATABASE_DSN => null]])]
    #[TestWith([[ConfigTransformerInterface::ENV_DATABASE_DSN => 42]])]
    public function testTransformWithMissingDatabaseDsn(array $env): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('%s not set or not a string', ConfigTransformerInterface::ENV_DATABASE_DSN));

        $functionConfigTransformer = self::createStub(FunctionConfigTransformerInterface::class);

        $transformer = new ConfigTransformer($functionConfigTransformer);
        $transformer->transform($env);
    }
}
