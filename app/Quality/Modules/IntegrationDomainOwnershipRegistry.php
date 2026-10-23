<?php

declare(strict_types=1);

namespace App\Quality\Modules;

use Illuminate\Contracts\Container\Container;

/**
 * Domain ownership registry for the 345 Integration services (KONSEP.md §A14.6, K-05, K-06).
 *
 * Verifies that all 345 services classified in tests/Architecture/baselines/integration-services-classification.json
 * and docs/INTEGRATION_SERVICES_CLASSIFICATION.md map 100% to valid owner modules in config/modules.php
 * and can be cleanly resolved by the Laravel service container.
 */
final class IntegrationDomainOwnershipRegistry
{
    public const string BASELINE_PATH = 'tests/Architecture/baselines/integration-services-classification.json';

    public const string DOC_PATH = 'docs/INTEGRATION_SERVICES_CLASSIFICATION.md';

    public const string SERVICES_NAMESPACE = 'Modules\\Integration\\Application\\Services\\';

    public const string ARCHIVE_TARGET = 'Arsip';

    /**
     * @return array<string, array{service: string, category: string, target: string, reason: string}>
     */
    public static function classifications(?string $root = null): array
    {
        $root ??= dirname(__DIR__, 3);
        $path = rtrim($root, '/').'/'.self::BASELINE_PATH;
        if (! is_file($path)) {
            return [];
        }

        /** @var array<string, array{service: string, category: string, target: string, reason: string}> $data */
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public static function validOwnerModules(array $config = []): array
    {
        $modules = array_unique(array_values($config['table_prefixes'] ?? config('modules.table_prefixes', [])));
        $modules[] = 'Core';
        $modules[] = 'Integration';
        $modules[] = 'Shared';
        $modules[] = self::ARCHIVE_TARGET;
        sort($modules);

        return array_values($modules);
    }

    /**
     * @return array<string, string> service name => target owner module
     */
    public static function domainOwnershipMap(?string $root = null): array
    {
        $classifications = self::classifications($root);
        $map = [];
        foreach ($classifications as $serviceName => $entry) {
            $map[$serviceName] = $entry['target'];
        }

        return $map;
    }

    /**
     * Validates that all 345 classified services map to a valid owner module.
     *
     * @param  array<string, mixed>  $config
     * @return array{valid: bool, count: int, unmapped: list<string>, invalid_targets: array<string, string>}
     */
    public static function validateOwnership(?string $root = null, array $config = []): array
    {
        $classifications = self::classifications($root);
        $validModules = self::validOwnerModules($config);
        $unmapped = [];
        $invalidTargets = [];

        foreach ($classifications as $serviceName => $entry) {
            $target = $entry['target'] ?? '';
            if ($target === '') {
                $unmapped[] = $serviceName;
            } elseif (! in_array($target, $validModules, true)) {
                $invalidTargets[$serviceName] = $target;
            }
        }

        return [
            'valid' => $unmapped === [] && $invalidTargets === [] && count($classifications) === 345,
            'count' => count($classifications),
            'unmapped' => $unmapped,
            'invalid_targets' => $invalidTargets,
        ];
    }

    /**
     * Tests container resolution for all 345 services.
     *
     * @return array{resolved_count: int, failed: array<string, string>}
     */
    public static function verifyContainerResolution(?Container $container = null, ?string $root = null): array
    {
        $container ??= app();
        $classifications = self::classifications($root);
        $failed = [];
        $resolvedCount = 0;

        foreach (array_keys($classifications) as $serviceName) {
            $class = self::SERVICES_NAMESPACE.$serviceName;
            try {
                $instance = $container->make($class);
                if ($instance instanceof $class) {
                    $resolvedCount++;
                } else {
                    $failed[$serviceName] = "Instance is not an object of {$class}";
                }
            } catch (\Throwable $e) {
                $failed[$serviceName] = $e->getMessage();
            }
        }

        return [
            'resolved_count' => $resolvedCount,
            'failed' => $failed,
        ];
    }
}
