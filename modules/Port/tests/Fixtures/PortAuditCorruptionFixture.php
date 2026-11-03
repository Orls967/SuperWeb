<?php

declare(strict_types=1);

namespace Modules\Port\Tests\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Port\Domain\Models\TerminalBill;
use Modules\Port\Domain\Models\YardBlock;
use Modules\Port\Domain\Models\YardSlot;

class PortAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'prt:audit';
    }

    public function corrupt(): string
    {
        $block = YardBlock::firstOrCreate(
            ['code' => 'YARD-CORRUPT-BLOCK'],
            ['name' => 'Block Corrupt Test', 'capacity_teu' => 100, 'block_type' => 'STANDARD']
        );

        $containerNo = 'CRX-CORRUPT-PORT-999';

        YardSlot::create([
            'yard_block_id' => $block->id,
            'container_no' => $containerNo,
            'slot_coordinate' => 'CORRUPT-01',
            'status' => 'IN_YARD',
        ]);

        YardSlot::create([
            'yard_block_id' => $block->id,
            'container_no' => $containerNo,
            'slot_coordinate' => 'CORRUPT-02',
            'status' => 'IN_YARD',
        ]);

        return $containerNo;
    }

    /**
     * Mengkorupsi invarian yard dengan memanipulasi slot aktif tanpa catatan gate pass.
     */
    public static function corruptYardMovement(): void
    {
        // Buat phantom slot yang tidak punya gate in
        $block = YardBlock::firstOrCreate(
            ['code' => 'YARD-BLOCK-DEFAULT'],
            ['name' => 'Default Block', 'capacity_teu' => 100, 'block_type' => 'STANDARD']
        );

        YardSlot::create([
            'yard_block_id' => $block->id,
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
                'amount_idr' => $bill->amount_idr + 5_000_000,
            ]);
        }
    }
}
