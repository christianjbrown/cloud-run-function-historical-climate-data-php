<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

interface ConfigTransformerInterface
{
    public const string ENV_DATABASE_DSN = 'CHRISTIANBROWN_DATABASE_DSN';

    /**
     * @param mixed[] $env
     */
    public function transform(array $env): ConfigInterface;
}
