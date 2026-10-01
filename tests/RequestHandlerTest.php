<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData\Tests;

use ChristianBrown\CloudRunFunction\CloudRunFunctionInterface;
use ChristianBrown\CloudRunFunction\FunctionConfigInterface;
use ChristianBrown\CloudRunFunction\JsonErrorResponseInterface;
use ChristianBrown\CloudRunFunction\JsonResponseFactoryInterface;
use ChristianBrown\CloudRunFunction\ResponseInterface as FunctionResponseInterface;
use ChristianBrown\HistoricalClimateData\CloudRunFunctionFactoryInterface;
use ChristianBrown\HistoricalClimateData\RequestHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function ini_set;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(RequestHandler::class)]
final class RequestHandlerTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testReturnsTheCloudRunFunctionResponseOnSuccess(): void
    {
        $request = self::createStub(ServerRequestInterface::class);
        $expectedResponse = self::createStub(FunctionResponseInterface::class);

        $cloudFunction = $this->createMock(CloudRunFunctionInterface::class);
        $cloudFunction->expects(self::once())
            ->method('run')
            ->with($request)
            ->willReturn($expectedResponse);

        $cloudFunctionFactory = $this->createMock(CloudRunFunctionFactoryInterface::class);
        $cloudFunctionFactory->expects(self::once())
            ->method('create')
            ->willReturn($cloudFunction);

        $functionConfig = self::createStub(FunctionConfigInterface::class);

        $requestHandler = new RequestHandler($cloudFunctionFactory, $functionConfig, self::createStub(JsonResponseFactoryInterface::class));

        self::assertSame($expectedResponse, $requestHandler->handle($request));
    }

    /**
     * @throws Exception
     */
    public function testReturnsTheJsonErrorEnvelopeWhenTheFactoryThrows(): void
    {
        $request = self::createStub(ServerRequestInterface::class);

        // A failure while building the entity manager / reader surfaces from the
        // factory, before the CloudRunFunction exists — the handler must convert it
        // into the framework's JSON error envelope, not let it escape as a bare 500.
        $cloudFunctionFactory = self::createStub(CloudRunFunctionFactoryInterface::class);
        $cloudFunctionFactory->method('create')
            ->willThrowException(new RuntimeException('database connection failed'));

        $functionConfig = self::createStub(FunctionConfigInterface::class);

        $errorResponse = self::createStub(FunctionResponseInterface::class);
        $responseFactory = self::createMock(JsonResponseFactoryInterface::class);
        $responseFactory->expects(self::once())
            ->method('error')
            ->with($functionConfig, CloudRunFunctionInterface::ERROR_UNHANDLED, JsonErrorResponseInterface::DEFAULT_ERROR_STATUS_CODE)
            ->willReturn($errorResponse);

        $requestHandler = new RequestHandler($cloudFunctionFactory, $functionConfig, $responseFactory);

        // The handler logs the cause via error_log() for Cloud Logging; divert it to a
        // temp file so the strict-output check does not see it as unexpected output.
        $errorLog = (string) tempnam(sys_get_temp_dir(), 'request-handler-test');
        $previousErrorLog = (string) ini_set('error_log', $errorLog);

        try {
            $response = $requestHandler->handle($request);
        } finally {
            ini_set('error_log', $previousErrorLog);
            unlink($errorLog);
        }

        self::assertSame($errorResponse, $response);
    }
}
