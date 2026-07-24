<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

use ChristianBrown\CloudRunFunction\FunctionConfigInterface;

interface ConfigInterface
{
    public function getDatabaseDsn(): string;

    public function getFunctionConfig(): FunctionConfigInterface;
}
