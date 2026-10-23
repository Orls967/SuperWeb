<?php

declare(strict_types=1);

namespace Tests\Architecture;

$_ENV['APP_BASE_PATH'] = dirname(__DIR__, 2);
$_SERVER['APP_BASE_PATH'] = dirname(__DIR__, 2);
putenv('APP_BASE_PATH='.dirname(__DIR__, 2));

use App\Quality\OpenApi\OpenApiContractValidator;
use App\Quality\OpenApi\OpenApiSpecGenerator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/*
| PROGRESS TAHAP 10B: OpenApiContractCompletenessTest.
| Memverifikasi kelengkapan dan sinkronisasi spesifikasi OpenAPI 3.1 lintas 46 modul:
| 1. Perintah `php artisan openapi:validate` mengembalikan exit code 0.
| 2. File docs/openapi-v3.json dan docs/openapi-v3.yaml ada, valid, dan menggunakan OpenAPI 3.1.0.
| 3. Seluruh 46 modul terdaftar di dalam tags OpenAPI.
| 4. MoneyMinorUnit didefinisikan secara ketat sebagai integer int64 (bukan float desimal IEEE 754).
| 5. Seluruh rute api/* tercatat di dalam paths lengkap dengan x-allowed-roles.
| 6. Seluruh operasi memiliki responses, security, dan x-allowed-roles valid.
*/

uses(TestCase::class);

it('ensures php artisan openapi:validate passes with exit code 0', function (): void {
    $exitCode = Artisan::call('openapi:validate');
    expect($exitCode)->toBe(0);

    $validator = app(OpenApiContractValidator::class);
    $result = $validator->validate();

    expect($result->isValid())->toBeTrue();
});

it('ensures docs/openapi-v3.json and docs/openapi-v3.yaml exist, are valid, and use OpenAPI 3.1.0', function (): void {
    $jsonPath = base_path('docs/openapi-v3.json');
    $yamlPath = base_path('docs/openapi-v3.yaml');

    expect(file_exists($jsonPath))->toBeTrue()
        ->and(file_exists($yamlPath))->toBeTrue();

    $jsonContent = (string) file_get_contents($jsonPath);
    $jsonSpec = json_decode($jsonContent, true);
    expect($jsonSpec)->toBeArray()
        ->and($jsonSpec['openapi'] ?? '')->toBe('3.1.0');

    $yamlContent = (string) file_get_contents($yamlPath);
    $yamlSpec = Yaml::parse($yamlContent);
    expect($yamlSpec)->toBeArray()
        ->and($yamlSpec['openapi'] ?? '')->toBe('3.1.0');
});

it('ensures all 46 canonical enterprise modules are present in tags', function (): void {
    $jsonPath = base_path('docs/openapi-v3.json');
    $jsonSpec = json_decode((string) file_get_contents($jsonPath), true);

    $tags = $jsonSpec['tags'] ?? [];
    $tagNames = array_column($tags, 'name');

    expect(count($tagNames))->toBeGreaterThanOrEqual(46);

    foreach (OpenApiSpecGenerator::CANONICAL_46_MODULES as $module) {
        expect($tagNames)->toContain($module);
    }
});

it('strictly defines MoneyMinorUnit as integer int64 and not float or decimal number', function (): void {
    $jsonPath = base_path('docs/openapi-v3.json');
    $jsonSpec = json_decode((string) file_get_contents($jsonPath), true);

    $schemas = $jsonSpec['components']['schemas'] ?? [];
    expect($schemas)->toHaveKey('MoneyMinorUnit');

    $money = $schemas['MoneyMinorUnit'];
    expect($money['type'] ?? '')->toBe('integer')
        ->and($money['format'] ?? '')->toBe('int64')
        ->and($money['type'] ?? '')->not->toBe('number')
        ->and($money['type'] ?? '')->not->toBe('float');
});

it('records every api/* application route in paths complete with x-allowed-roles', function (): void {
    $jsonPath = base_path('docs/openapi-v3.json');
    $jsonSpec = json_decode((string) file_get_contents($jsonPath), true);
    $paths = $jsonSpec['paths'] ?? [];

    $routes = Route::getRoutes()->getRoutes();
    $apiRoutesFound = 0;

    foreach ($routes as $route) {
        $uri = $route->uri();

        if (! str_starts_with($uri, 'api/')) {
            continue;
        }

        $openApiPath = '/'.ltrim($uri, '/');
        $methods = array_values(array_diff($route->methods(), ['HEAD']));

        expect($paths)->toHaveKey($openApiPath);

        foreach ($methods as $method) {
            $methodLower = strtolower($method);
            expect($paths[$openApiPath])->toHaveKey($methodLower);

            $operation = $paths[$openApiPath][$methodLower];
            expect($operation)->toHaveKey('x-allowed-roles')
                ->and($operation['x-allowed-roles'])->toBeArray()->not->toBeEmpty()
                ->and($operation)->toHaveKey('operationId')
                ->and($operation['operationId'])->toBeString()->not->toBeEmpty();
        }

        $apiRoutesFound++;
    }

    expect($apiRoutesFound)->toBeGreaterThanOrEqual(15);
});

it('ensures every operation defines responses, security, and allowed roles', function (): void {
    $jsonPath = base_path('docs/openapi-v3.json');
    $jsonSpec = json_decode((string) file_get_contents($jsonPath), true);
    $paths = $jsonSpec['paths'] ?? [];

    expect($paths)->not->toBeEmpty();

    foreach ($paths as $path => $methods) {
        foreach ($methods as $method => $op) {
            expect($op)->toHaveKey('operationId')
                ->and($op)->toHaveKey('tags')
                ->and($op['tags'])->toBeArray()->not->toBeEmpty()
                ->and($op)->toHaveKey('x-allowed-roles')
                ->and($op['x-allowed-roles'])->toBeArray()->not->toBeEmpty()
                ->and($op)->toHaveKey('responses')
                ->and($op['responses'])->toBeArray()->not->toBeEmpty()
                ->and($op)->toHaveKey('security')
                ->and($op['security'])->toBeArray();

            if (in_array($method, ['post', 'put', 'patch'], true)) {
                expect($op)->toHaveKey('requestBody');
            }
        }
    }
});

it('verifies docs/openapi-v3.json and docs/openapi-v3.yaml are in complete sync with generated spec', function (): void {
    $generator = app(OpenApiSpecGenerator::class);
    expect($generator->isSynchronized())->toBeTrue();
});
