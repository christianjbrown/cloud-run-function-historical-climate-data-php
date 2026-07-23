<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

interface QueryInterface
{
    public function getMonthsBack(): int;

    public function getResolution(): string;
}
