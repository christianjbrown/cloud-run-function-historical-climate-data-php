<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

interface RouteRegistryInterface
{
    /**
     * The query a route name stands for, or null when the route is not defined.
     */
    public function find(string $route): ?QueryInterface;
}
