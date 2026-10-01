<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

/**
 * @phpstan-import-type ClimateBucket from OutputTransformerInterface
 */
interface BucketKeyGeneratorInterface
{
    /**
     * A chronologically sortable key for a bucket: `YYYY-MM-DD` for daily,
     * `YYYY-MM-DDTHH` for hourly.
     *
     * @param ClimateBucket $bucket
     */
    public function generate(array $bucket, string $resolution): string;
}
