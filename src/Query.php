<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

final class Query implements QueryInterface
{
    private int $monthsBack;
    private string $resolution;

    public function __construct(string $resolution, int $monthsBack)
    {
        $this->resolution = $resolution;
        $this->monthsBack = $monthsBack;
    }

    public function getMonthsBack(): int
    {
        return $this->monthsBack;
    }

    public function getResolution(): string
    {
        return $this->resolution;
    }
}
