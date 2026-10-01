<?php

declare(strict_types=1);

date_default_timezone_set('UTC');

use ChristianBrown\CloudRunFunction\CloudRunFunction;
use ChristianBrown\CloudRunFunction\CloudRunFunctionInterface;
use ChristianBrown\CloudRunFunction\FunctionConfigTransformer;
use ChristianBrown\Database\ClimateHistoryReader;
use ChristianBrown\Database\DbalClimateQueryRunner;
use ChristianBrown\Database\Entity\MetOfficeWeather;
use ChristianBrown\Database\Entity\SmartThingsClimate;
use ChristianBrown\Database\EntityManagerFactory;
use ChristianBrown\HistoricalClimateData\BucketKeyGenerator;
use ChristianBrown\HistoricalClimateData\CloudRunFunctionFactoryInterface;
use ChristianBrown\HistoricalClimateData\ConfigInterface;
use ChristianBrown\HistoricalClimateData\ConfigTransformer;
use ChristianBrown\HistoricalClimateData\DataProvider;
use ChristianBrown\HistoricalClimateData\OutputTransformer;
use ChristianBrown\HistoricalClimateData\Query;
use ChristianBrown\HistoricalClimateData\QueryParser;
use ChristianBrown\HistoricalClimateData\RequestHandler;
use ChristianBrown\HistoricalClimateData\RouteRegistry;
use ChristianBrown\HistoricalClimateData\TwoDecimalRounder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Clock\NativeClock;

function run(ServerRequestInterface $request): ResponseInterface
{
    $env = getenv();
    $functionConfigTransformer = new FunctionConfigTransformer();
    $configTransformer = new ConfigTransformer($functionConfigTransformer);
    $config = $configTransformer->transform($env);

    // The entity manager / reader construction happens inside the factory (not
    // here) so that RequestHandler::handle() wraps it in the same try/catch as
    // CloudRunFunction::run() and a failure there returns the framework's JSON error
    // envelope rather than escaping as a bare 500.
    $cloudFunctionFactory = new class($config) implements CloudRunFunctionFactoryInterface
    {
        private ConfigInterface $config;

        public function __construct(ConfigInterface $config)
        {
            $this->config = $config;
        }

        public function create(): CloudRunFunctionInterface
        {
            $config = $this->config;

            $entityManager = (new EntityManagerFactory($config->getDatabaseDsn()))->getEntityManager();
            $reader = new ClimateHistoryReader(new DbalClimateQueryRunner($entityManager->getConnection()));

            // Table names come from the shared entity mapping (single source of truth).
            $insideTable = $entityManager->getClassMetadata(SmartThingsClimate::class)->getTableName();
            $outsideTable = $entityManager->getClassMetadata(MetOfficeWeather::class)->getTableName();

            // Curated whitelist of {resolution}-{lookback}. Hourly is capped at a
            // day/month; the longer windows are daily only.
            $routeRegistry = new RouteRegistry([
                'hourly-day' => new Query(ClimateHistoryReaderInterface::RESOLUTION_HOURLY, 'P1D'),
                'hourly-1-month' => new Query(ClimateHistoryReaderInterface::RESOLUTION_HOURLY, 'P1M'),
                'daily-1-month' => new Query(ClimateHistoryReaderInterface::RESOLUTION_DAILY, 'P1M'),
                'daily-3-month' => new Query(ClimateHistoryReaderInterface::RESOLUTION_DAILY, 'P3M'),
                'daily-6-month' => new Query(ClimateHistoryReaderInterface::RESOLUTION_DAILY, 'P6M'),
                'daily-12-month' => new Query(ClimateHistoryReaderInterface::RESOLUTION_DAILY, 'P1Y'),
            ]);

            $dataProvider = new DataProvider(
                $reader,
                new QueryParser($routeRegistry),
                new OutputTransformer(new BucketKeyGenerator(), new TwoDecimalRounder()),
                new NativeClock(),
                $insideTable,
                $outsideTable
            );

            return new CloudRunFunction($dataProvider, $config->getFunctionConfig());
        }
    };

    $requestHandler = new RequestHandler($cloudFunctionFactory, $config->getFunctionConfig());

    return $requestHandler->handle($request);
}
