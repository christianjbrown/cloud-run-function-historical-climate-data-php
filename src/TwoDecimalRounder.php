<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

use function round;

final class TwoDecimalRounder implements ValueRounderInterface
{
    public function round(?float $value): ?float
    {
        return null === $value ? null : round($value, 2);
    }
}
