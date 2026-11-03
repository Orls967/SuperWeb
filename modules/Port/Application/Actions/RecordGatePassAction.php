<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Port\Domain\Models\GatePass;
use Modules\Port\Domain\Models\YardBlock;
use Modules\Port\Domain\Models\YardSlot;

class RecordGatePassAction
{
    /**
     * Memproses Gate In kontainer ke terminal.
     */
    public function gateIn(
        string $containerNo,
        int $linePartyId,
        string $vehicleRegNo,
        ?int $yardBlockId = null,
        ?string $slotCoordinate = null
    ): GatePass {
        return DB::transaction(function () use ($containerNo, $linePartyId, $vehicleRegNo, $yardBlockId, $slotCoordinate) {
            // Invarian integritas: kontainer yang sedang IN_YARD tidak boleh gate-in lagi (pencegahan double gate-in)
            $existingActiveSlot = YardSlot::where('container_no', $containerNo)
                ->where('status', 'IN_YARD')
                ->first();

            if ($existingActiveSlot !== null) {
                throw new DomainException("Kontainer {$containerNo} sudah berada di lapangan penumpukan (IN_YARD). Gate-in ganda ditolak.");
            }

            $passNumber = 'GP-IN-'.strtoupper(bin2hex(random_bytes(4)));

            $gatePass = GatePass::create([
                'pass_number' => $passNumber,
                'container_no' => $containerNo,
                'pass_type' => 'IN',
                'vehicle_reg_no' => $vehicleRegNo,
                'line_party_id' => $linePartyId,
                'issued_at' => now(),
                'completed_at' => now(),
                'status' => 'COMPLETED',
            ]);

            // Tentukan yard block default jika tidak dispesifikasikan
            $blockId = $yardBlockId;
            if ($blockId === null) {
                $block = YardBlock::firstOrCreate(
                    ['code' => 'YB-A'],
                    ['name' => 'Yard Block A', 'capacity_teu' => 500, 'block_type' => 'STANDARD']
                );
                $blockId = $block->id;
            }

            YardSlot::create([
                'yard_block_id' => $blockId,
                'container_no' => $containerNo,
                'slot_coordinate' => $slotCoordinate ?? 'A-01-01-1',
                'status' => 'IN_YARD',
                'gate_in_at' => now(),
            ]);

            return $gatePass;
        });
    }

    /**
     * Memproses Gate Out kontainer dari terminal.
     */
    public function gateOut(
        string $containerNo,
        int $linePartyId,
        string $vehicleRegNo
    ): GatePass {
        return DB::transaction(function () use ($containerNo, $linePartyId, $vehicleRegNo) {
            // Kontainer harus berstatus IN_YARD
            $activeSlot = YardSlot::where('container_no', $containerNo)
                ->where('status', 'IN_YARD')
                ->latest('id')
                ->first();

            if ($activeSlot === null) {
                throw new DomainException("Kontainer {$containerNo} tidak ditemukan di lapangan penumpukan aktif. Gate-out ditolak.");
            }

            $passNumber = 'GP-OUT-'.strtoupper(bin2hex(random_bytes(4)));

            $gatePass = GatePass::create([
                'pass_number' => $passNumber,
                'container_no' => $containerNo,
                'pass_type' => 'OUT',
                'vehicle_reg_no' => $vehicleRegNo,
                'line_party_id' => $linePartyId,
                'issued_at' => now(),
                'completed_at' => now(),
                'status' => 'COMPLETED',
            ]);

            $activeSlot->update([
                'status' => 'GATED_OUT',
                'gate_out_at' => now(),
            ]);

            return $gatePass;
        });
    }
}
