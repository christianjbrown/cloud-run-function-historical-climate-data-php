<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData\Tests;

use ChristianBrown\GcpFunction\CloudFunctionInterface;
use ChristianBrown\GcpFunction\FunctionConfigInterface;
use ChristianBrown\GcpFunction\JsonErrorResponse;
use ChristianBrown\GcpFunction\JsonErrorResponseInterface;
use ChristianBrown\HistoricalClimateData\CloudFunctionFactoryInterface;
use ChristianBrown\HistoricalClimateData\RequestHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
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
    public function testReturnsTheCloudFunctionResponseOnSuccess(): void
    {
        $request = self::createStub(ServerRequestInterface::class);
        $expectedResponse = self::createStub(ResponseInterface::class);

        $cloudFunction = $this->createMock(CloudFunctionInterface::class);
        $cloudFunction->expects(self::once())
            ->method('run')
            ->with($request)
            ->willReturn($expectedResponse);

        $cloudFunctionFactory = $this->createMock(CloudFunctionFactoryInterface::class);
        $cloudFunctionFactory->expects(self::once())
            ->method('create')
            ->willReturn($cloudFunction);

        $functionConfig = self::createStub(FunctionConfigInterface::class);

        $requestHandler = new RequestHandler($cloudFunctionFactory, $functionConfig);

        self::assertSame($expectedResponse, $requestHandler->handle($request));
    }

    /**
     * @throws Exception
     */
    public function testReturnsTheJsonErrorEnvelopeWhenTheFactoryThrows(): void
    {
        $request = self::createStub(ServerRequestInterface::class);

        // A failure while building the entity manager / reader surfaces from the
        // factory, before the CloudFunction exists — the handler must convert it
        // into the framework's JSON error envelope, not let it escape as a bare 500.
        $cloudFunctionFactory = self::createStub(CloudFunctionFactoryInterface::class);
        $cloudFunctionFactory->method('create')
            ->willThrowException(new RuntimeException('database connection failed'));

        $functionConfig = self::createStub(FunctionConfigInterface::class);

        $requestHandler = new RequestHandler($cloudFunctionFactory, $functionConfig);

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

        self::assertInstanceOf(JsonErrorResponse::class, $response);
        self::assertSame(JsonErrorResponseInterface::DEFAULT_ERROR_STATUS_CODE, $response->getStatusCode());
    }
}
