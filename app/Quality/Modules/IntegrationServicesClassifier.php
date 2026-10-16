<?php

declare(strict_types=1);

namespace App\Quality\Modules;

final class IntegrationServicesClassifier
{
    public const BASELINE_PATH = 'tests/Architecture/baselines/integration-services-classification.json';

    public const SERVICES_DIR = 'modules/Integration/Application/Services';

    /**
     * @return array<string, array{service: string, category: string, target: string, reason: string}>
     */
    public static function load(string $root): array
    {
        $path = rtrim($root, '/').'/'.self::BASELINE_PATH;
        if (! is_file($path)) {
            return [];
        }

        /** @var array<string, array{service: string, category: string, target: string, reason: string}> $data */
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }

    /**
     * Returns list of existing service class names in modules/Integration/Application/Services.
     *
     * @return list<string>
     */
    public static function diskServices(string $root): array
    {
        $dir = rtrim($root, '/').'/'.self::SERVICES_DIR;
        if (! is_dir($dir)) {
            return [];
        }

        $files = glob($dir.'/*.php') ?: [];
        $names = array_map(fn (string $f): string => basename($f, '.php'), $files);
        sort($names);

        return array_values($names);
    }
}
