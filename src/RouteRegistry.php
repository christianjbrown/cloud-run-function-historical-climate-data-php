<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

final class RouteRegistry implements RouteRegistryInterface
{
    /**
     * @var array<string, QueryInterface>
     */
    private array $queries;

    /**
     * @param array<string, QueryInterface> $queries route name to the query it serves
     */
    public function __construct(array $queries)
    {
        $this->queries = $queries;
    }

    public function find(string $route): ?QueryInterface
    {
        return $this->queries[$route] ?? null;
    }
}
