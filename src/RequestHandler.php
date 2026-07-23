<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData;

use ChristianBrown\GcpFunction\CloudFunctionInterface;
use ChristianBrown\GcpFunction\FunctionConfigInterface;
use ChristianBrown\GcpFunction\JsonErrorResponse;
use ChristianBrown\GcpFunction\JsonErrorResponseInterface;
use ChristianBrown\GcpFunction\ResponseInterface as FunctionResponseInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function error_log;

final class RequestHandler implements RequestHandlerInterface
{
    private CloudFunctionFactoryInterface $cloudFunctionFactory;
    private FunctionConfigInterface $functionConfig;

    public function __construct(CloudFunctionFactoryInterface $cloudFunctionFactory, FunctionConfigInterface $functionConfig)
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
            // CloudFunction::run(), so a failure there would otherwise escape as a
            // bare 500. Log the cause for Cloud Logging and return the framework's
            // JSON error envelope instead, keeping the response contract consistent —
            // the CDN's stale-if-error still shields visitors with the last good copy.
            error_log((string) $exception);
            $requestOrigin = $request->getHeaderLine(FunctionResponseInterface::HEADER_KEY_ORIGIN);

            return new JsonErrorResponse($this->functionConfig, CloudFunctionInterface::ERROR_UNHANDLED, JsonErrorResponseInterface::DEFAULT_ERROR_STATUS_CODE, $requestOrigin);
        }
    }
}
