<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Port\Application\Services\PortFleetLedgerService;
use Modules\Port\Domain\Models\FleetLedgerEntry;

final class PrtFleetAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'prt:fleet-audit';
    }

    public function corrupt(): string
    {
        $corruptCode = 'FLT-CORRUPT-FIXTURE-999';

        FleetLedgerEntry::create([
            'entry_code' => $corruptCode,
            'voyage_id' => null,
            'vessel_id' => null,
            'account_code' => PortFleetLedgerService::ACC_EXPENSE_BUNKER,
            'entry_type' => 'DEBIT',
            'amount' => 50000.0,
            'currency' => 'USD',
            'reference_type' => 'AUDIT_CORRUPTION_TEST',
            'reference_id' => 9999,
            'description' => 'Fixture entri sabotase audit untuk PrtFleetAuditCorruptionFixture',
            'checksum' => 'INVALID_TAMPERED_CHECKSUM',
        ]);

        return 'AUDIT GAGAL';
    }
}
