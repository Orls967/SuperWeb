<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Chaos;

use Modules\Platform\Domain\Models\SandboxEnvironment;

/**
 * Simulator manipulasi integritas basis data untuk membuktikan deteksi 100% pada platform:audit.
 */
class DatabaseTamperSimulator
{
    /**
     * Memodifikasi status sandbox secara ilegal melewati State Machine
     */
    public function injectCorruptSandboxStatus(SandboxEnvironment $sandbox, string $illegalStatus = 'corrupted_state'): bool
    {
        return (bool) $sandbox->newQuery()
            ->where('id', $sandbox->id)
            ->update(['status' => $illegalStatus]);
    }

    /**
     * Merusak hash sertifikat pada snapshot metrik
     */
    public function tamperMetricCertificate(string $phaseId): bool
    {
        return (bool) \Illuminate\Support\Facades\DB::table('plt_phase_metrics')
            ->where('phase_id', $phaseId)
            ->update(['certificate_hash' => 'tampered_invalid_sha256_hash_value_000000']);
    }
}

