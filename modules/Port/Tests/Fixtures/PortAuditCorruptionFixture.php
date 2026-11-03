<?php

declare(strict_types=1);

namespace Modules\Port\Tests\Fixtures;

use Modules\Port\Domain\Models\TerminalBill;
use Modules\Port\Domain\Models\YardSlot;

class PortAuditCorruptionFixture
{
    /**
     * Mengkorupsi invarian yard dengan memanipulasi slot aktif tanpa catatan gate pass.
     */
    public static function corruptYardMovement(): void
    {
        // Buat phantom slot yang tidak punya gate in
        YardSlot::create([
            'yard_block_id' => 1,
            'container_no' => 'GHOST-CONT-999',
            'slot_coordinate' => 'Z-99-99-1',
            'status' => 'IN_YARD',
            'gate_in_at' => now(),
        ]);
    }

    /**
     * Mengkorupsi konsistensi penagihan dengan mendistorsi amount total dari komponennya.
     */
    public static function corruptBillingAmount(): void
    {
        $bill = TerminalBill::latest('id')->first();
        if ($bill) {
            $bill->update([
                'amount_idr' => $bill->amount_idr + 5_000_000, // distorsi selisih Rp 5jt
            ]);
        }
    }
}
