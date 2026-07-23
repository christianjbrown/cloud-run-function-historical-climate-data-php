<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

final class Query implements QueryInterface
{
    private string $lookback;
    private string $resolution;

    public function __construct(string $resolution, string $lookback)
    {
        $this->resolution = $resolution;
        $this->lookback = $lookback;
    }

    public function getLookback(): string
    {
        return $this->lookback;
    }

    public function getResolution(): string
    {
        return $this->resolution;
    }
}
