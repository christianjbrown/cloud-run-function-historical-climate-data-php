<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

use ChristianBrown\GcpFunction\FunctionConfigInterface;

interface ConfigInterface
{
    public function getDatabaseDsn(): string;

    public function getFunctionConfig(): FunctionConfigInterface;
}
