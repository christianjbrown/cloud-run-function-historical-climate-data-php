<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

use OpenApi\Attributes as OA;

/**
 * @phpstan-type ClimateBucket array{date: string, hour: int|null, minTemperature: float|null, maxTemperature: float|null, minHumidity: float|null, maxHumidity: float|null}
 */
#[OA\Schema(
    schema: 'ClimateHistoryBucket',
    description: 'One time bucket. `date` is always present; `hour` is present only for the hourly resolutions. The eight inside/outside min/max fields are always present but are null for a side that reported no data in that bucket. Values are rounded to two decimals.',
    required: [
        'date',
        'insideMaxTemp',
        'insideMinTemp',
        'insideMinHumidity',
        'insideMaxHumidity',
        'outsideMaxTemp',
        'outsideMinTemp',
        'outsideMinHumidity',
        'outsideMaxHumidity',
    ],
    properties: [
        new OA\Property(property: 'date', description: 'The bucket date (UTC), as `YYYY-MM-DD`.', type: 'string', format: 'date'),
        new OA\Property(property: 'hour', description: 'The bucket hour (UTC, 0-23). Present only for the hourly resolutions.', type: 'integer'),
        new OA\Property(property: 'insideMaxTemp', description: 'Highest inside (SmartThings) temperature in the bucket (degrees Celsius).', type: 'number', nullable: true),
        new OA\Property(property: 'insideMinTemp', description: 'Lowest inside temperature in the bucket (degrees Celsius).', type: 'number', nullable: true),
        new OA\Property(property: 'insideMinHumidity', description: 'Lowest inside relative humidity in the bucket (percent).', type: 'number', nullable: true),
        new OA\Property(property: 'insideMaxHumidity', description: 'Highest inside relative humidity in the bucket (percent).', type: 'number', nullable: true),
        new OA\Property(property: 'outsideMaxTemp', description: 'Highest outside (Met Office) temperature in the bucket (degrees Celsius).', type: 'number', nullable: true),
        new OA\Property(property: 'outsideMinTemp', description: 'Lowest outside temperature in the bucket (degrees Celsius).', type: 'number', nullable: true),
        new OA\Property(property: 'outsideMinHumidity', description: 'Lowest outside relative humidity in the bucket (percent).', type: 'number', nullable: true),
        new OA\Property(property: 'outsideMaxHumidity', description: 'Highest outside relative humidity in the bucket (percent).', type: 'number', nullable: true),
    ],
    type: 'object',
    additionalProperties: false,
)]
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
