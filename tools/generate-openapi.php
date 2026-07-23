<?php

declare(strict_types=1);

use OpenApi\Annotations\OpenApi;
use OpenApi\Generator;

require __DIR__ . '/../vendor/autoload.php';

// Scan the typed `#[OA\...]` attributes under `src/` — plus the shared envelope
// schema components (`SuccessEnvelope`/`ErrorEnvelope`) that live on the
// `php-gcp-function-lib` `ResponseInterface` — and emit the OpenAPI 3.0 document.
// Pinning the version to 3.0.0 keeps it inside the broadest validator support.
// The result is written to the committed `openapi.yaml`; CI regenerates it and
// fails on any diff, so the spec cannot drift from the attributes.
$openapi = (new Generator())
    ->setVersion(OpenApi::VERSION_3_0_0)
    ->generate([
        __DIR__ . '/../src',
        __DIR__ . '/../vendor/christianjbrown/php-gcp-function-lib/src',
    ]);

if (!$openapi instanceof OpenApi) {
    fwrite(STDERR, "Failed to generate the OpenAPI document from src/.\n");

    exit(1);
}

file_put_contents(__DIR__ . '/../openapi.yaml', $openapi->toYaml());
