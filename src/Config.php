<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

use ChristianBrown\CloudRunFunction\FunctionConfigInterface;

final class Config implements ConfigInterface
{
    private string $databaseDsn;
    private FunctionConfigInterface $functionConfig;

    public function __construct(FunctionConfigInterface $functionConfig, string $databaseDsn)
    {
        $this->functionConfig = $functionConfig;
        $this->databaseDsn = $databaseDsn;
    }

    public function getDatabaseDsn(): string
    {
        return $this->databaseDsn;
    }

    public function getFunctionConfig(): FunctionConfigInterface
    {
        return $this->functionConfig;
    }
}
