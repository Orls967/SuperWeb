<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Port\Domain\Models\YardBlock;
use Modules\Port\Domain\Models\YardSlot;

final class PrtAuditCorruptionFixture implements CorruptionFixture
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
}
