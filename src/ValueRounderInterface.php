<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

interface ValueRounderInterface
{
    public function round(?float $value): ?float;
}
