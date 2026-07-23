<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

use ChristianBrown\GcpFunction\CloudFunctionInterface;

interface CloudFunctionFactoryInterface
{
    public function create(): CloudFunctionInterface;
}
