<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

use ChristianBrown\Database\ClimateHistoryReaderInterface;

use function sprintf;

/**
 * @phpstan-import-type ClimateBucket from OutputTransformerInterface
 */
final class BucketKeyGenerator implements BucketKeyGeneratorInterface
{
    /**
     * @param ClimateBucket $bucket
     */
    public function generate(array $bucket, string $resolution): string
    {
        if (ClimateHistoryReaderInterface::RESOLUTION_HOURLY === $resolution) {
            return sprintf('%sT%02d', $bucket['date'], (int) $bucket['hour']);
        }

        return $bucket['date'];
    }
}
