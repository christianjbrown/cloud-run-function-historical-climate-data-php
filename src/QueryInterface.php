<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

interface QueryInterface
{
    /**
     * The lookback window as an ISO-8601 duration (e.g. `P1D`, `P1M`, `P1Y`).
     */
    public function getLookback(): string;

    public function getResolution(): string;
}
