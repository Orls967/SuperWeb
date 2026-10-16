<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;

final class SecurityAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'security:audit';
    }

    public function corrupt(): string
    {
        DB::table('sec_security_incidents')->insert([
            'incident_code' => 'INC-CRIT-999',
            'incident_class' => 'RANSOMWARE',
            'severity' => 'P1',
            'status' => 'DETECTED', // Insiden aktif tanpa mitigasi membuat security:audit gagal
            'affected_lines' => json_encode(['HOS', 'FIN']),
            'description' => 'Simulasi insiden kritis keamanan untuk fixture audit',
            'detected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'security:audit FAILED';
    }
}
