<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature\Resilience;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\DTOs\Resilience\DrillExecutionDto;
use Modules\Core\Application\DTOs\Resilience\KmsRotationDto;
use Modules\Core\Application\Services\Resilience\DataSovereigntyComplianceService;
use Modules\Core\Application\Services\Resilience\DisasterRecoveryDrillService;
use Tests\TestCase;

class DisasterRecoveryAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dr_audit_succeeds_on_clean_state_and_catches_divergence_and_tampered_kms_keys(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // 1. Clean audit passes
        $this->artisan('dr:audit')
            ->expectsOutputToContain('dr:audit PASSED')
            ->assertSuccessful();

        // 2. Perform valid drill & KMS rotation
        /** @var DisasterRecoveryDrillService $drillService */
        $drillService = app(DisasterRecoveryDrillService::class);
        $drill = $drillService->executeDrill(new DrillExecutionDto(
            drillType: 'FAILOVER_SIMULATION',
            primaryRegion: 'ap-southeast-3',
            targetDrRegion: 'ap-southeast-1',
            simulatedLagSeconds: 10,
            userId: $admin->id
        ));

        /** @var DataSovereigntyComplianceService $kmsService */
        $kmsService = app(DataSovereigntyComplianceService::class);
        $key = $kmsService->rotateKmsKey(new KmsRotationDto(
            keyAlias: 'master-database-envelope-key',
            encryptionAlgorithm: 'AES-256-GCM',
            userId: $admin->id
        ));

        $this->artisan('dr:audit')
            ->expectsOutputToContain('dr:audit PASSED')
            ->assertSuccessful();

        // 3. Inject tampered KMS key fingerprint
        $key->update(['key_fingerprint' => 'tampered_forged_sha256_kms_fingerprint']);

        $this->artisan('dr:audit')
            ->expectsOutputToContain('dr:audit FAILED')
            ->assertExitCode(1);

        // Fix KMS key
        $key->update([
            'key_fingerprint' => hash('sha256', sprintf('kms:%s:v%d:%s:entropy-vault-2026', $key->key_alias, $key->key_version, $key->encryption_algorithm)),
        ]);

        // 4. Inject DR divergence
        $drill->update(['divergence_count' => 3]);

        $this->artisan('dr:audit')
            ->expectsOutputToContain('dr:audit FAILED')
            ->assertExitCode(1);
    }
}
