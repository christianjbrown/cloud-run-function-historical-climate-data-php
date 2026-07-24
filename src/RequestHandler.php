<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

use ChristianBrown\CloudRunFunction\CloudRunFunctionInterface;
use ChristianBrown\CloudRunFunction\FunctionConfigInterface;
use ChristianBrown\CloudRunFunction\JsonErrorResponse;
use ChristianBrown\CloudRunFunction\JsonErrorResponseInterface;
use ChristianBrown\CloudRunFunction\ResponseInterface as FunctionResponseInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function error_log;

final class RequestHandler implements RequestHandlerInterface
{
    private CloudRunFunctionFactoryInterface $cloudFunctionFactory;
    private FunctionConfigInterface $functionConfig;

    public function __construct(CloudRunFunctionFactoryInterface $cloudFunctionFactory, FunctionConfigInterface $functionConfig)
    {
        $this->cloudFunctionFactory = $cloudFunctionFactory;
        $this->functionConfig = $functionConfig;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $cloudFunction = $this->cloudFunctionFactory->create();

            return $cloudFunction->run($request);
        } catch (Throwable $exception) {
            // Building the entity manager / reader happens in the factory, outside
            // CloudRunFunction::run(), so a failure there would otherwise escape as a
            // bare 500. Log the cause for Cloud Logging and return the framework's
            // JSON error envelope instead, keeping the response contract consistent —
            // the CDN's stale-if-error still shields visitors with the last good copy.
            error_log((string) $exception);
            $requestOrigin = $request->getHeaderLine(FunctionResponseInterface::HEADER_KEY_ORIGIN);

            return new JsonErrorResponse($this->functionConfig, CloudRunFunctionInterface::ERROR_UNHANDLED, JsonErrorResponseInterface::DEFAULT_ERROR_STATUS_CODE, $requestOrigin);
        }
    }
}
