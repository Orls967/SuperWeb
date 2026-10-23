<?php

declare(strict_types=1);

namespace App\Quality\OpenApi;

use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\Yaml\Yaml;

final class OpenApiContractValidator
{
    /**
     * @param  array<string, mixed>|null  $routeRolesMap
     */
    public function __construct(
        private readonly ?array $routeRolesMap = null,
    ) {}

    /**
     * Validate OpenAPI 3.1 specification files against application routes and contract invariants.
     */
    public function validate(?string $basePath = null): OpenApiValidationResult
    {
        $root = $basePath ?? base_path();
        $jsonPath = $root.'/docs/openapi-v3.json';
        $yamlPath = $root.'/docs/openapi-v3.yaml';

        $errors = [];
        $warnings = [];

        // 1. File existence
        if (! file_exists($jsonPath)) {
            $errors[] = "File spesifikasi OpenAPI JSON '{$jsonPath}' tidak ditemukan. Jalankan 'php artisan openapi:generate'.";
        }

        if (! file_exists($yamlPath)) {
            $errors[] = "File spesifikasi OpenAPI YAML '{$yamlPath}' tidak ditemukan. Jalankan 'php artisan openapi:generate'.";
        }

        if (! empty($errors)) {
            return new OpenApiValidationResult(false, $errors, $warnings, []);
        }

        // 2. Syntax & Parsing
        $jsonContent = (string) file_get_contents($jsonPath);
        $jsonSpec = json_decode($jsonContent, true);

        if (! is_array($jsonSpec) || json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = 'docs/openapi-v3.json bukan dokumen JSON yang valid: '.json_last_error_msg();

            return new OpenApiValidationResult(false, $errors, $warnings, []);
        }

        try {
            $yamlContent = (string) file_get_contents($yamlPath);
            /** @var array<string, mixed> $yamlSpec */
            $yamlSpec = Yaml::parse($yamlContent);
        } catch (\Throwable $e) {
            $errors[] = 'docs/openapi-v3.yaml bukan dokumen YAML yang valid: '.$e->getMessage();

            return new OpenApiValidationResult(false, $errors, $warnings, []);
        }

        // 3. OpenAPI Version 3.1.0
        if (($jsonSpec['openapi'] ?? '') !== '3.1.0') {
            $errors[] = "Versi OpenAPI pada docs/openapi-v3.json bukan '3.1.0' (ditemukan: ".($jsonSpec['openapi'] ?? 'null').').';
        }

        if (($yamlSpec['openapi'] ?? '') !== '3.1.0') {
            $errors[] = "Versi OpenAPI pada docs/openapi-v3.yaml bukan '3.1.0' (ditemukan: ".($yamlSpec['openapi'] ?? 'null').').';
        }

        // 4. Info Section & Invariants
        $info = $jsonSpec['info'] ?? [];
        if (empty($info['title']) || empty($info['version'])) {
            $errors[] = 'docs/openapi-v3.json: bagian info wajib memiliki title dan version.';
        }

        $infoDesc = (string) ($info['description'] ?? '');
        if (! str_contains($infoDesc, 'MoneyMinorUnit') && ! str_contains($infoDesc, 'minor-unit')) {
            $warnings[] = 'docs/openapi-v3.json: info.description sebaiknya mendokumentasikan arsitektur integer minor-unit.';
        }
        if (! str_contains($infoDesc, 'Idempotency') && ! str_contains($infoDesc, 'idempotensi')) {
            $warnings[] = 'docs/openapi-v3.json: info.description sebaiknya mendokumentasikan jaminan idempotensi deterministik.';
        }

        // 5. Components: Security Schemes
        $securitySchemes = $jsonSpec['components']['securitySchemes'] ?? [];
        $requiredSecurity = ['BearerAuth', 'SessionCookieAuth', 'HmacWebhookSignature', 'MtlsClientCert'];
        foreach ($requiredSecurity as $sec) {
            if (! isset($securitySchemes[$sec])) {
                $errors[] = "components.securitySchemes wajib mendefinisikan '{$sec}'.";
            }
        }

        // 6. Components: Parameters
        $parameters = $jsonSpec['components']['parameters'] ?? [];
        $requiredParams = ['IdempotencyKeyHeader', 'CorrelationIdHeader', 'CursorPaginationParam'];
        foreach ($requiredParams as $param) {
            if (! isset($parameters[$param])) {
                $errors[] = "components.parameters wajib mendefinisikan '{$param}'.";
            }
        }

        // 7. Components: Schemas & MoneyMinorUnit strictness
        $schemas = $jsonSpec['components']['schemas'] ?? [];
        $requiredSchemas = ['MoneyMinorUnit', 'HashChainProof', 'ProblemDetailsRfc7807', 'PaginatedEnvelope'];
        foreach ($requiredSchemas as $schema) {
            if (! isset($schemas[$schema])) {
                $errors[] = "components.schemas wajib mendefinisikan '{$schema}'.";
            }
        }

        if (isset($schemas['MoneyMinorUnit'])) {
            $mType = $schemas['MoneyMinorUnit']['type'] ?? null;
            $mFormat = $schemas['MoneyMinorUnit']['format'] ?? null;

            if ($mType !== 'integer' || $mFormat !== 'int64') {
                $errors[] = "MoneyMinorUnit WAJIB berformat integer int64 (ditemukan: type={$mType}, format={$mFormat}). Dilarang menggunakan float/number desimal.";
            }
        }

        // 8. Tags Coverage across 46 Modules
        $tagObjects = $jsonSpec['tags'] ?? [];
        $registeredTagNames = [];
        foreach ($tagObjects as $tagObj) {
            if (isset($tagObj['name']) && is_string($tagObj['name'])) {
                $registeredTagNames[] = $tagObj['name'];
            }
        }

        $canonical46 = OpenApiSpecGenerator::CANONICAL_46_MODULES;
        $missingCanonicalTags = array_diff($canonical46, $registeredTagNames);
        if (! empty($missingCanonicalTags)) {
            $errors[] = 'Daftar tags OpenAPI belum memuat modul kanonikal: '.implode(', ', $missingCanonicalTags);
        }

        // 9. Paths Introspection & Completeness
        $paths = $jsonSpec['paths'] ?? [];
        $routes = RouteFacade::getRoutes()->getRoutes();
        $roleMap = $this->routeRolesMap ?? $this->loadRouteRolesMap();

        $apiRouteCount = 0;
        $totalOperations = 0;
        $missingApiRoutes = [];
        $operationsWithoutRoles = [];
        $operationsWithoutResponses = [];
        $operationsWithoutSecurity = [];
        $operationsWithoutRequestBody = [];
        $operationsMissingPathParams = [];

        foreach ($routes as $route) {
            $uri = $route->uri();

            if (str_starts_with($uri, '_boost') || str_starts_with($uri, '_ignition')) {
                continue;
            }

            $openApiPath = '/'.ltrim($uri, '/');
            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            $isApiRoute = str_starts_with($uri, 'api/');

            if ($isApiRoute) {
                $apiRouteCount++;
            }

            if (! isset($paths[$openApiPath])) {
                if ($isApiRoute) {
                    $missingApiRoutes[] = "{$openApiPath} (".implode('|', $methods).')';
                }

                continue;
            }

            foreach ($methods as $method) {
                $methodLower = strtolower($method);

                if (! isset($paths[$openApiPath][$methodLower])) {
                    if ($isApiRoute) {
                        $missingApiRoutes[] = "{$method} {$openApiPath}";
                    }

                    continue;
                }

                $totalOperations++;
                $op = $paths[$openApiPath][$methodLower];

                // Validate x-allowed-roles
                $allowedRoles = $op['x-allowed-roles'] ?? null;
                if (! is_array($allowedRoles) || empty($allowedRoles)) {
                    $operationsWithoutRoles[] = "{$methodLower} {$openApiPath}";
                }

                // Validate responses
                $responses = $op['responses'] ?? null;
                if (! is_array($responses) || empty($responses)) {
                    $operationsWithoutResponses[] = "{$methodLower} {$openApiPath}";
                } else {
                    $hasSuccess = isset($responses['200']) || isset($responses['201']);
                    if (! $hasSuccess) {
                        $operationsWithoutResponses[] = "{$methodLower} {$openApiPath} (missing 200/201)";
                    }
                }

                // Validate security
                if (! array_key_exists('security', $op) || ! is_array($op['security'])) {
                    $operationsWithoutSecurity[] = "{$methodLower} {$openApiPath}";
                }

                // Validate requestBody for mutations
                if (in_array($methodLower, ['post', 'put', 'patch'], true)) {
                    if (! isset($op['requestBody']) || ! is_array($op['requestBody'])) {
                        $operationsWithoutRequestBody[] = "{$methodLower} {$openApiPath}";
                    }
                }

                // Validate path parameters
                if (preg_match_all('#\{([a-zA-Z0-9_]+)\}#', $openApiPath, $matches)) {
                    $opParams = $op['parameters'] ?? [];
                    $definedPathParams = [];
                    foreach ($opParams as $p) {
                        if (is_array($p) && ($p['in'] ?? '') === 'path' && isset($p['name'])) {
                            $definedPathParams[] = $p['name'];
                        }
                    }

                    $missingP = array_diff($matches[1], $definedPathParams);
                    if (! empty($missingP)) {
                        $operationsMissingPathParams[] = "{$methodLower} {$openApiPath} (missing path params: ".implode(',', $missingP).')';
                    }
                }
            }
        }

        if (! empty($missingApiRoutes)) {
            $errors[] = 'Rute api/* berikut belum terdaftar di dalam paths docs/openapi-v3.json: '.implode('; ', array_slice($missingApiRoutes, 0, 10));
        }

        if (! empty($operationsWithoutRoles)) {
            $errors[] = 'Ditemukan operasi tanpa x-allowed-roles: '.implode('; ', array_slice($operationsWithoutRoles, 0, 5));
        }

        if (! empty($operationsWithoutResponses)) {
            $errors[] = 'Ditemukan operasi tanpa definisi responses standar: '.implode('; ', array_slice($operationsWithoutResponses, 0, 5));
        }

        if (! empty($operationsWithoutSecurity)) {
            $errors[] = 'Ditemukan operasi tanpa definisi security: '.implode('; ', array_slice($operationsWithoutSecurity, 0, 5));
        }

        if (! empty($operationsWithoutRequestBody)) {
            $errors[] = 'Ditemukan operasi POST/PUT/PATCH tanpa requestBody: '.implode('; ', array_slice($operationsWithoutRequestBody, 0, 5));
        }

        if (! empty($operationsMissingPathParams)) {
            $errors[] = 'Ditemukan parameter URL rute yang tidak didefinisikan di parameters: '.implode('; ', array_slice($operationsMissingPathParams, 0, 5));
        }

        $stats = [
            'total_paths' => count($paths),
            'total_operations' => $totalOperations,
            'total_tags' => count($registeredTagNames),
            'canonical_modules_covered' => count($canonical46),
            'api_routes_checked' => $apiRouteCount,
            'json_size_bytes' => strlen($jsonContent),
            'yaml_size_bytes' => strlen($yamlContent),
        ];

        return new OpenApiValidationResult(
            empty($errors),
            $errors,
            $warnings,
            $stats
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function loadRouteRolesMap(): array
    {
        $path = base_path('tests/Architecture/route-roles.php');
        if (file_exists($path)) {
            /** @var array<string, mixed> $map */
            $map = require $path;

            return $map;
        }

        return [];
    }
}
