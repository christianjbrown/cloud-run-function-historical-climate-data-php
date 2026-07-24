<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

use ChristianBrown\CloudRunFunction\CloudRunFunctionInterface;

interface CloudRunFunctionFactoryInterface
{
    public function create(): CloudRunFunctionInterface;
}
