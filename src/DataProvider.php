<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

use ChristianBrown\Database\ClimateHistoryReaderInterface;
use DateInterval;
use DateTimeImmutable;
use Psr\Http\Message\ServerRequestInterface;

final class DataProvider implements DataProviderInterface
{
    private string $insideTable;
    private DateTimeImmutable $now;
    private OutputTransformerInterface $outputTransformer;
    private string $outsideTable;
    private QueryParserInterface $queryParser;
    private ClimateHistoryReaderInterface $reader;

    public function __construct(ClimateHistoryReaderInterface $reader, QueryParserInterface $queryParser, OutputTransformerInterface $outputTransformer, string $insideTable, string $outsideTable)
    {
        $this->reader = $reader;
        $this->queryParser = $queryParser;
        $this->outputTransformer = $outputTransformer;
        $this->insideTable = $insideTable;
        $this->outsideTable = $outsideTable;
        $this->now = new DateTimeImmutable();
    }

    /**
     * @return mixed[]
     */
    public function getData(ServerRequestInterface $request): array
    {
        $query = $this->queryParser->parse($request->getUri()->getPath());

        // The window runs from the query's lookback before now up to now (UTC).
        $start = $this->now->sub(new DateInterval($query->getLookback()));

        $inside = $this->reader->read($this->insideTable, $query->getResolution(), $start, $this->now);
        $outside = $this->reader->read($this->outsideTable, $query->getResolution(), $start, $this->now);

        return $this->outputTransformer->transform($inside, $outside, $query->getResolution());
    }
}
