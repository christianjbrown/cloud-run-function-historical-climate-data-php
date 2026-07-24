<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData\Tests;

use ChristianBrown\Database\ClimateHistoryReaderInterface;
use ChristianBrown\CloudRunFunction\CloudRunFunction;
use ChristianBrown\CloudRunFunction\DataProviderInterface as BaseDataProviderInterface;
use ChristianBrown\CloudRunFunction\FunctionConfig;
use ChristianBrown\CloudRunFunction\FunctionConfigInterface;
use ChristianBrown\HistoricalClimateData\CloudRunFunctionFactoryInterface;
use ChristianBrown\HistoricalClimateData\OutputTransformer;
use ChristianBrown\HistoricalClimateData\QueryParserInterface;
use ChristianBrown\HistoricalClimateData\RequestHandler;
use ChristianBrown\UserFriendlyException\UserFriendlyException;
use GuzzleHttp\Psr7\ServerRequest;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function dirname;

/**
 * Validates the function's real PSR-7 responses against the committed
 * `openapi.yaml` (generated from the `#[OA\...]` attributes). If a response ever
 * drifts from the contract — an unexpected key, a wrong type, a missing required
 * field — `ResponseValidator::validate()` throws and the suite fails.
 */
#[CoversClass(RequestHandler::class)]
#[UsesClass(OutputTransformer::class)]
final class ContractTest extends TestCase
{
    private const string ORIGIN = 'https://example.com';
    private const string REVISION = 'contract-test-revision';
    private const string ROUTE = '/daily-1-month';
    private ResponseValidator $responseValidator;

    protected function setUp(): void
    {
        $this->responseValidator = (new ValidatorBuilder())
            ->fromYamlFile(dirname(__DIR__).'/openapi.yaml')
            ->getResponseValidator();
    }

    /**
     * @throws Exception
     */
    public function testInvalidPathErrorResponseMatchesContract(): void
    {
        $dataProvider = self::createStub(BaseDataProviderInterface::class);
        $dataProvider->method('getData')
            ->willThrowException(new UserFriendlyException(QueryParserInterface::ERROR_INVALID_PATH));

        $response = $this->buildResponse($this->unauthenticatedConfig(), $dataProvider, new ServerRequest('GET', '/not-a-route'));

        $this->responseValidator->validate(new OperationAddress('/{route}', 'get'), $response);
        self::assertSame(500, $response->getStatusCode());
    }

    /**
     * @throws Exception
     */
    public function testSuccessDailyPayloadMatchesContract(): void
    {
        $inside = [
            self::bucket('2026-07-19', null, 19.3, 24.1, 41.2, 58.7),
            self::bucket('2026-07-20', null, 20.1, 25.0, 39.5, 55.0),
        ];
        // Only the first day is present outside, so 2026-07-20 keeps null outside fields.
        $outside = [self::bucket('2026-07-19', null, 11.8, 22.6, 44.0, 89.0)];

        $data = (new OutputTransformer())->transform($inside, $outside, ClimateHistoryReaderInterface::RESOLUTION_DAILY);

        $response = $this->buildResponse($this->unauthenticatedConfig(), $this->dataProvider($data), new ServerRequest('GET', self::ROUTE, ['Origin' => self::ORIGIN]));

        $this->responseValidator->validate(new OperationAddress('/{route}', 'get'), $response);
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @throws Exception
     */
    public function testSuccessEmptyPayloadMatchesContract(): void
    {
        $data = (new OutputTransformer())->transform([], [], ClimateHistoryReaderInterface::RESOLUTION_DAILY);

        $response = $this->buildResponse($this->unauthenticatedConfig(), $this->dataProvider($data), new ServerRequest('GET', self::ROUTE));

        $this->responseValidator->validate(new OperationAddress('/{route}', 'get'), $response);
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @throws Exception
     */
    public function testSuccessHourlyPayloadMatchesContract(): void
    {
        $inside = [self::bucket('2026-07-20', 14, 22.4, 23.9, 45.0, 52.0)];
        $outside = [self::bucket('2026-07-20', 14, 18.0, 21.0, 40.0, 70.0)];

        $data = (new OutputTransformer())->transform($inside, $outside, ClimateHistoryReaderInterface::RESOLUTION_HOURLY);

        $response = $this->buildResponse($this->unauthenticatedConfig(), $this->dataProvider($data), new ServerRequest('GET', '/hourly-day', ['Origin' => self::ORIGIN]));

        $this->responseValidator->validate(new OperationAddress('/{route}', 'get'), $response);
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @throws Exception
     */
    public function testUnauthorizedResponseMatchesContract(): void
    {
        $config = (new FunctionConfig(self::REVISION))
            ->setRequiredHeaderKey('X-Request-Auth')
            ->setRequiredHeaderValue('secret');

        $dataProvider = self::createStub(BaseDataProviderInterface::class);

        $response = $this->buildResponse($config, $dataProvider, new ServerRequest('GET', self::ROUTE));

        $this->responseValidator->validate(new OperationAddress('/{route}', 'get'), $response);
        self::assertSame(401, $response->getStatusCode());
    }

    /**
     * @return array{date: string, hour: null|int, minTemperature: null|float, maxTemperature: null|float, minHumidity: null|float, maxHumidity: null|float}
     */
    private static function bucket(string $date, ?int $hour, ?float $minTemperature, ?float $maxTemperature, ?float $minHumidity, ?float $maxHumidity): array
    {
        return [
            'date' => $date,
            'hour' => $hour,
            'minTemperature' => $minTemperature,
            'maxTemperature' => $maxTemperature,
            'minHumidity' => $minHumidity,
            'maxHumidity' => $maxHumidity,
        ];
    }

    /**
     * @throws Exception
     */
    private function buildResponse(FunctionConfigInterface $config, BaseDataProviderInterface $dataProvider, ServerRequestInterface $request): ResponseInterface
    {
        $cloudFunction = new CloudRunFunction($dataProvider, $config);

        $cloudFunctionFactory = self::createStub(CloudRunFunctionFactoryInterface::class);
        $cloudFunctionFactory->method('create')
            ->willReturn($cloudFunction);

        $requestHandler = new RequestHandler($cloudFunctionFactory, $config);

        return $requestHandler->handle($request);
    }

    /**
     * @param mixed[] $data
     *
     * @throws Exception
     */
    private function dataProvider(array $data): BaseDataProviderInterface
    {
        $dataProvider = self::createStub(BaseDataProviderInterface::class);
        $dataProvider->method('getData')
            ->willReturn($data);

        return $dataProvider;
    }

    private function unauthenticatedConfig(): FunctionConfigInterface
    {
        return (new FunctionConfig(self::REVISION))
            ->setAllowUnauthenticated(true)
            ->setRequiredOrigin(self::ORIGIN)
            ->setUseCacheTtl(3600)
            ->setUseCacheButRequestTtl(600)
            ->setUseCacheIfErrorTtl(86400);
    }
}
