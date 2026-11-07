<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Venue\Domain\Models\BiometricCredential;

final class VenCrowdAuditCorruptionFixture implements CorruptionFixture
{
    public function __construct(
        private readonly string $commandName = 'venue:audit-crowd'
    ) {}

    public function command(): string
    {
        return $this->commandName;
    }

    public function corrupt(): string
    {
        // Sabotase: Masukkan kredensial biometrik tanpa persetujuan (consented_at = null)
        BiometricCredential::query()->create([
            'credential_code' => 'BIO-CORRUPT-SABOTAGE-001',
            'user_id' => 9999,
            'biometric_template_hash' => hash('sha256', 'CORRUPT_PROBE_DATA'),
            'liveness_signature_hash' => hash('sha256', 'FAKE_LIVENESS'),
            'liveness_score_bps' => 9000,
            'sensor_model' => 'ROGUE-SENSOR',
            'status' => 'ENROLLED_ACTIVE',
            'consented_at' => null, // Pelanggaran privasi UU PDP!
            'is_active' => true,
        ]);

        return 'AUDIT KESELAMATAN KERUMUNAN VENUE GAGAL';
    }
}
