<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Insurance\Domain\Enums\CessionKind;
use Modules\Insurance\Domain\Models\CessionEvent;

final class ReinsAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'ins:reinsurance-audit';
    }

    public function corrupt(): string
    {
        // Korupsi invariant: gross != retained + ceded
        CessionEvent::create([
            'event_key' => 'corrupt:reins:audit:'.uniqid(),
            'kind' => CessionKind::Premium,
            'period_ym' => '2026-11',
            'gross_idr' => 10000000,
            'retained_idr' => 3000000,
            'ceded_idr' => 2000000, // 3jt + 2jt = 5jt != 10jt!
            'commission_idr' => 0,
            'reinstatement_idr' => 0,
            'snapshot' => ['corrupted' => true],
            'snapshot_hash' => 'hash-corrupted',
            'occurred_at' => now(),
        ]);

        return 'Audit reasuransi GAGAL';
    }
}
