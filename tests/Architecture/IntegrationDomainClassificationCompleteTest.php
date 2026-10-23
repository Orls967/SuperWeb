<?php

declare(strict_types=1);

use App\Quality\Modules\IntegrationDomainOwnershipRegistry;
use Tests\TestCase;

/*
| PROGRESS R4.4 / TAHAP 13 (K-05, K-06, KONSEP §A14.6):
| Integration Domain Classification and Ownership Complete Test.
| Verifies that all 345 services in integration-services-classification.json and
| docs/INTEGRATION_SERVICES_CLASSIFICATION.md 100% map to valid owner modules in
| config/modules.php and resolve cleanly in Laravel's service container.
*/

uses(TestCase::class);

it('verifies that all 345 services are classified and map 100% to valid owner modules', function (): void {
    $root = dirname(__DIR__, 2);
    $validation = IntegrationDomainOwnershipRegistry::validateOwnership($root);

    expect($validation['count'])->toBe(345, 'Harus ada tepat 345 service yang terdaftar di baseline klasifikasi.')
        ->and($validation['unmapped'])->toBeEmpty('Ada service yang tidak terpetakan ke modul pemilik.')
        ->and($validation['invalid_targets'])->toBeEmpty('Ada service dengan target modul yang tidak valid di config/modules.php.')
        ->and($validation['valid'])->toBeTrue('Validasi kepemilikan domain 345 service harus valid 100%.');
});

it('verifies that all 345 services in docs/INTEGRATION_SERVICES_CLASSIFICATION.md match the baseline', function (): void {
    $root = dirname(__DIR__, 2);
    $docPath = $root.'/'.IntegrationDomainOwnershipRegistry::DOC_PATH;

    expect(is_file($docPath))->toBeTrue('Dokumen docs/INTEGRATION_SERVICES_CLASSIFICATION.md wajib ada.');

    $docContent = (string) file_get_contents($docPath);
    $classifications = IntegrationDomainOwnershipRegistry::classifications($root);

    expect(count($classifications))->toBe(345);

    foreach ($classifications as $serviceName => $entry) {
        expect(str_contains($docContent, "`{$serviceName}`"))->toBeTrue(
            "Service {$serviceName} tidak tercatat di docs/INTEGRATION_SERVICES_CLASSIFICATION.md."
        );
        expect(str_contains($docContent, "`{$entry['target']}`"))->toBeTrue(
            "Target modul {$entry['target']} untuk {$serviceName} tidak tercatat di docs/INTEGRATION_SERVICES_CLASSIFICATION.md."
        );
    }
});

it('verifies that all 345 Integration services resolve cleanly via the Laravel container without error', function (): void {
    $resolution = IntegrationDomainOwnershipRegistry::verifyContainerResolution();

    expect($resolution['resolved_count'])->toBe(345, 'Seluruh 345 service harus dapat di-resolve oleh container.')
        ->and($resolution['failed'])->toBeEmpty('Ada service yang gagal di-resolve oleh container: '.json_encode($resolution['failed']));
});
