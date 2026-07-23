<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

use ChristianBrown\Database\ClimateHistoryReaderInterface;

use function array_combine;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function mb_substr;
use function sort;
use function sprintf;

/**
 * @phpstan-import-type ClimateBucket from OutputTransformerInterface
 */
final class OutputTransformer implements OutputTransformerInterface
{
    /**
     * @param list<ClimateBucket> $inside
     * @param list<ClimateBucket> $outside
     *
     * @return mixed[]
     */
    public function transform(array $inside, array $outside, string $resolution): array
    {
        $insideByKey = $this->indexByBucketKey($inside, $resolution);
        $outsideByKey = $this->indexByBucketKey($outside, $resolution);

        // A chronologically-sortable key ('YYYY-MM-DD' or 'YYYY-MM-DDTHH') means a
        // plain string sort of the union yields the required earliest-first order.
        $keys = array_values(array_unique(array_merge(array_keys($insideByKey), array_keys($outsideByKey))));
        sort($keys);

        return array_map(
            fn (string $key): array => $this->buildRow($insideByKey[$key] ?? null, $outsideByKey[$key] ?? null, $key, $resolution),
            $keys
        );
    }

    /**
     * @param ClimateBucket $bucket
     */
    private function bucketKey(array $bucket, string $resolution): string
    {
        if (ClimateHistoryReaderInterface::RESOLUTION_HOURLY === $resolution) {
            return sprintf('%sT%02d', $bucket['date'], (int) $bucket['hour']);
        }

        return $bucket['date'];
    }

    /**
     * @param null|ClimateBucket $inside
     * @param null|ClimateBucket $outside
     *
     * @return mixed[]
     */
    private function buildRow(?array $inside, ?array $outside, string $key, string $resolution): array
    {
        // The date (and hour) come from the sortable key, so no reference bucket is
        // needed when only one side is present.
        $row = ['date' => mb_substr($key, 0, 10)];
        if (ClimateHistoryReaderInterface::RESOLUTION_HOURLY === $resolution) {
            $row['hour'] = (int) mb_substr($key, 11, 2);
        }

        // `+` preserves insertion order, giving the fixed date[, hour], inside*,
        // outside* field order.
        return $row + $this->side($inside, 'inside') + $this->side($outside, 'outside');
    }

    /**
     * @param list<ClimateBucket> $buckets
     *
     * @return array<string, ClimateBucket>
     */
    private function indexByBucketKey(array $buckets, string $resolution): array
    {
        $keys = array_map(
            fn (array $bucket): string => $this->bucketKey($bucket, $resolution),
            $buckets
        );

        return array_combine($keys, $buckets);
    }

    /**
     * @param null|ClimateBucket $bucket
     * @param 'inside'|'outside' $prefix
     *
     * @return array<string, null|float>
     */
    private function side(?array $bucket, string $prefix): array
    {
        // Branch once (not per field) so a missing side is a single path — four
        // independent ternaries would explode the path-coverage combinations.
        if (null === $bucket) {
            return [
                $prefix.'MaxTemp' => null,
                $prefix.'MinTemp' => null,
                $prefix.'MinHumidity' => null,
                $prefix.'MaxHumidity' => null,
            ];
        }

        return [
            $prefix.'MaxTemp' => $bucket['maxTemperature'],
            $prefix.'MinTemp' => $bucket['minTemperature'],
            $prefix.'MinHumidity' => $bucket['minHumidity'],
            $prefix.'MaxHumidity' => $bucket['maxHumidity'],
        ];
    }
}
