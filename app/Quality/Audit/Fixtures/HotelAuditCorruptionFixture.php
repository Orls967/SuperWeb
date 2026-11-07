<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\Contracts\AuditCorruptionFixture;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Hotel\Domain\Models\HotelDigitalKey;
use Modules\Hotel\Domain\Models\HotelReservation;

final class HotelAuditCorruptionFixture implements AuditCorruptionFixture
{
    public function commandSignature(): string
    {
        return 'hotel:audit';
    }

    public function corrupt(): void
    {
        // Sabotase integritas audit: ubah status kunci digital yang sudah checkout menjadi active
        $checkedOutRes = HotelReservation::where('status', 'checked_out')->first();
        if ($checkedOutRes !== null) {
            HotelDigitalKey::where('reservation_id', $checkedOutRes->id)->update([
                'status' => 'active',
            ]);
        } else {
            // Alternatif: korupsi amount di ledger entry agar tidak seimbang
            $entry = LedgerEntry::first();
            if ($entry !== null) {
                DB::table('bank_ledger_entries')
                    ->where('id', $entry->id)
                    ->update(['amount' => $entry->amount + 999999]);
            }
        }
    }
}
