<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

use ChristianBrown\Database\ClimateHistoryReaderInterface;
use DateInterval;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DataProvider implements DataProviderInterface
{
    private ClockInterface $clock;
    private string $insideTable;
    private OutputTransformerInterface $outputTransformer;
    private string $outsideTable;
    private QueryParserInterface $queryParser;
    private ClimateHistoryReaderInterface $reader;

    public function __construct(ClimateHistoryReaderInterface $reader, QueryParserInterface $queryParser, OutputTransformerInterface $outputTransformer, ClockInterface $clock, string $insideTable, string $outsideTable)
    {
        $this->reader = $reader;
        $this->queryParser = $queryParser;
        $this->outputTransformer = $outputTransformer;
        $this->clock = $clock;
        $this->insideTable = $insideTable;
        $this->outsideTable = $outsideTable;
    }

    /**
     * @return mixed[]
     */
    public function getData(ServerRequestInterface $request): array
    {
        $query = $this->queryParser->parse($request->getUri()->getPath());

        $now = $this->clock->now();

        // The window runs from the query's lookback before now up to now (UTC).
        $start = $now->sub(new DateInterval($query->getLookback()));

        $inside = $this->reader->read($this->insideTable, $query->getResolution(), $start, $now);
        $outside = $this->reader->read($this->outsideTable, $query->getResolution(), $start, $now);

        return $this->outputTransformer->transform($inside, $outside, $query->getResolution());
    }
}
