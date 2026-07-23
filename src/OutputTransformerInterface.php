<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

/**
 * @phpstan-type ClimateBucket array{date: string, hour: int|null, minTemperature: float|null, maxTemperature: float|null, minHumidity: float|null, maxHumidity: float|null}
 */
interface OutputTransformerInterface
{
    /**
     * Merges the inside (SmartThings) and outside (Met Office) per-bucket min/max
     * into one row per bucket, ordered earliest first. A bucket present on only
     * one side keeps null for the other side's four fields.
     *
     * @param list<ClimateBucket> $inside
     * @param list<ClimateBucket> $outside
     *
     * @return mixed[]
     */
    public function transform(array $inside, array $outside, string $resolution): array;
}
