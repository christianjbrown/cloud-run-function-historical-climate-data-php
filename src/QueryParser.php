<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

use ChristianBrown\CloudRunFunction\BadRequestException;

use function preg_match;

final class QueryParser implements QueryParserInterface
{
    private RouteRegistryInterface $routeRegistry;

    public function __construct(RouteRegistryInterface $routeRegistry)
    {
        $this->routeRegistry = $routeRegistry;
    }

    public function parse(string $path): QueryInterface
    {
        // Grab the trailing route segment, tolerant of any route prefix
        // Fastly/Cloud Run may leave in front of it.
        if (1 !== preg_match('#(?:^|/)([a-z0-9-]+)$#', $path, $matches)) {
            throw new BadRequestException(self::ERROR_INVALID_PATH);
        }

        return $this->routeRegistry->find($matches[1]) ?? throw new BadRequestException(self::ERROR_INVALID_PATH);
    }
}
