<?php

declare(strict_types=1);

namespace ChristianBrown\HistoricalClimateData\Tests;

use GuzzleHttp\Psr7\ServerRequest;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function array_intersect_key;
use function dirname;
use function file_get_contents;
use function ini_set;
use function json_decode;
use function putenv;
use function run;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Boots the real entry point, index.php, the way Cloud Run does: fake settings and a database that
 * refuses connections. index.php wires the whole object graph by hand and no other test loads it, so
 * a broken constructor call or a missing import there would otherwise only show up as a 500 after a
 * deploy. Getting as far as the database and answering with the framework's JSON error envelope
 * proves the wiring holds together.
 */
#[CoversNothing]
final class EntryPointTest extends TestCase
{
    private const string AUTH_HEADER = 'X-Request-Auth';
    private const string AUTH_VALUE = 'entry-point-test-secret';

    #[RunInSeparateProcess]
    public function testBootsAndAnswersWithTheErrorEnvelopeWhenTheDatabaseIsUnreachable(): void
    {
        // Port 1 on loopback refuses at once, so the test never waits on a network timeout.
        putenv('CHRISTIANBROWN_DATABASE_DSN=mysql://user:password@127.0.0.1:1/schema?driver=pdo_mysql');
        putenv('K_REVISION=entry-point-test');
        putenv('REQUIRED_HEADER_KEY='.self::AUTH_HEADER);
        putenv('REQUIRED_HEADER_VALUE='.self::AUTH_VALUE);
        // The function logs the failure with error_log(); keep it out of the test's output and check it.
        $errorLog = (string) tempnam(sys_get_temp_dir(), 'entry-point-test');
        ini_set('error_log', $errorLog);

        include dirname(__DIR__).'/index.php';

        $response = run(new ServerRequest('GET', 'https://example.com/daily-1-month', [self::AUTH_HEADER => self::AUTH_VALUE]));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(
            ['error' => 'An unhandled error occurred', 'success' => false, 'version' => 'entry-point-test'],
            array_intersect_key((array) json_decode((string) $response->getBody(), true), ['error' => true, 'success' => true, 'version' => true])
        );
        (new ValidatorBuilder())
            ->fromYamlFile(dirname(__DIR__).'/openapi.yaml')
            ->getResponseValidator()
            ->validate(new OperationAddress('/{route}', 'get'), $response);
        self::assertStringContainsString('Connection refused', (string) file_get_contents($errorLog));
        unlink($errorLog);
    }
}
