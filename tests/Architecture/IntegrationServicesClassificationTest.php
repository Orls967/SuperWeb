<?php

declare(strict_types=1);

use App\Quality\Modules\IntegrationServicesClassifier;
use Modules\Integration\Application\Services\IntegrationService;
use Modules\Integration\Application\Services\ZeroTrustService;
use Modules\Integration\IntegrationServiceProvider;
use Tests\TestCase;

/*
| PROGRESS R4.4 (K-05, K-06, KONSEP §A14.6): Integration services classification.
| Verifies all 345 services in modules/Integration/Application/Services are classified,
| IntegrationServiceProvider is emptied of unconfigured singletons, and services auto-resolve.
*/

uses(TestCase::class);

it('classifies all 345 services in modules/Integration/Application/Services', function (): void {
    $root = dirname(__DIR__, 2);
    $diskServices = IntegrationServicesClassifier::diskServices($root);
    $classification = IntegrationServicesClassifier::load($root);

    expect($diskServices)->toHaveCount(345)
        ->and(array_keys($classification))->toHaveCount(345);

    $diff = array_diff($diskServices, array_keys($classification));
    expect($diff)->toBeEmpty('Semua service di modules/Integration/Application/Services wajib terdaftar di baseline klasifikasi.');

    $allowedPrefixes = [
        'pertahankan di Integration',
        'pindah ke modul',
        'arsipkan/hapus (izin pemilik)',
    ];

    $knownModules = array_unique(array_values(config('modules.table_prefixes', [])));
    $knownModules[] = 'Core';
    $knownModules[] = 'Shared';
    $knownModules[] = 'Integration';
    $knownModules[] = 'Arsip';

    foreach ($classification as $serviceName => $entry) {
        expect($entry['service'])->toBe($serviceName)
            ->and($entry['category'])->not->toBeEmpty()
            ->and($entry['target'])->not->toBeEmpty()
            ->and($entry['reason'])->not->toBeEmpty();

        $validCategory = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($entry['category'], $prefix)) {
                $validCategory = true;
                break;
            }
        }
        expect($validCategory)->toBeTrue("Kategori untuk {$serviceName} tidak valid: {$entry['category']}");

        expect(in_array($entry['target'], $knownModules, true))->toBeTrue("Target modul {$entry['target']} untuk {$serviceName} tidak dikenal.");
    }
});

it('verifies IntegrationServiceProvider has no unconfigured singletons', function (): void {
    $root = dirname(__DIR__, 2);
    $providerPath = $root.'/modules/Integration/IntegrationServiceProvider.php';
    $content = (string) file_get_contents($providerPath);

    expect(str_contains($content, '$this->app->singleton'))->toBeFalse(
        'IntegrationServiceProvider::register() harus dikosongkan dari singleton tanpa konfigurasi (R4.4).'
    );
});

it('ensures docs/INTEGRATION_SERVICES_CLASSIFICATION.md exists and documents all 345 services', function (): void {
    $root = dirname(__DIR__, 2);
    $docPath = $root.'/docs/INTEGRATION_SERVICES_CLASSIFICATION.md';

    expect(is_file($docPath))->toBeTrue('docs/INTEGRATION_SERVICES_CLASSIFICATION.md wajib ada.');

    $docContent = (string) file_get_contents($docPath);
    $classification = IntegrationServicesClassifier::load($root);

    foreach (array_keys($classification) as $serviceName) {
        expect(str_contains($docContent, "`{$serviceName}`"))->toBeTrue(
            "Service {$serviceName} belum tercatat di docs/INTEGRATION_SERVICES_CLASSIFICATION.md."
        );
    }
});

it('confirms Integration services auto-resolve seamlessly via service container', function (): void {
    $integrationService = app(IntegrationService::class);
    expect($integrationService)->toBeInstanceOf(IntegrationService::class);

    $zeroTrustService = app(ZeroTrustService::class);
    expect($zeroTrustService)->toBeInstanceOf(ZeroTrustService::class);
});
