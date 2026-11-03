<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Port\Domain\Models\Berth;
use Modules\Port\Domain\Models\GatePass;
use Modules\Port\Domain\Models\TerminalBill;
use Modules\Port\Domain\Models\VesselCall;
use Modules\Port\Domain\Models\YardBlock;
use Modules\Port\Domain\Models\YardSlot;

class PortTerminalT1Seeder extends Seeder
{
    /**
     * Menghasilkan dataset skala T1: 50 kunjungan kapal, 2.000 gerakan kontainer, 500 TEU aktif di yard.
     */
    public function run(): void
    {
        // 1. Buat 5 Tambatan
        $berths = [];
        for ($b = 1; $b <= 5; $b++) {
            $berths[] = Berth::firstOrCreate(
                ['code' => "B-0{$b}"],
                [
                    'name' => "Dermaga {$b}",
                    'length_meters' => 250 + ($b * 20),
                    'max_draft_meters' => 14.0 + ($b * 0.2),
                    'status' => 'AVAILABLE',
                ]
            );
        }

        // 2. Buat 5 Blok Lapangan Penumpukan
        $blocks = [];
        $blockCodes = ['YB-A', 'YB-B', 'YB-C', 'YB-D', 'YB-REEFER'];
        foreach ($blockCodes as $idx => $code) {
            $blocks[] = YardBlock::firstOrCreate(
                ['code' => $code],
                [
                    'name' => "Blok {$code}",
                    'capacity_teu' => 1000,
                    'block_type' => str_contains($code, 'REEFER') ? 'REEFER' : 'STANDARD',
                ]
            );
        }

        // 3. 50 Kunjungan Kapal
        $vesselCalls = [];
        for ($v = 1; $v <= 50; $v++) {
            $callNumber = sprintf('VC-2026-%04d', $v);
            $berth = $berths[($v - 1) % count($berths)];
            $linePartyId = ($v % 5) + 1; // 5 shipping lines

            $vesselCalls[] = VesselCall::firstOrCreate(
                ['call_number' => $callNumber],
                [
                    'vessel_name' => "MV Ocean Carrier {$v}",
                    'imo_number' => sprintf('IMO920%04d', $v),
                    'line_party_id' => $linePartyId,
                    'eta' => now()->subDays(rand(1, 30)),
                    'etd' => now()->addDays(rand(1, 5)),
                    'berth_id' => $berth->id,
                    'status' => $v <= 10 ? 'AT_BERTH' : 'DEPARTED',
                    'weight' => rand(1, 3),
                    'duration_hours' => rand(12, 48),
                ]
            );
        }

        // 4. 2.000 Gerakan Kontainer:
        // Invarian: Total Gate In = Total Gate Out + Kontainer aktif di Yard
        // Misal: 1.500 Gate In, 1.000 Gate Out, 500 masih aktif IN_YARD (Total gerakan: 1.500 IN + 1.000 OUT = 2.500)
        $totalIn = 1500;
        $totalOut = 1000;
        $activeInYard = $totalIn - $totalOut; // 500

        DB::beginTransaction();
        try {
            // Bulk insert Gate In & Yard Slots
            $gateInPasses = [];
            $yardSlots = [];
            $now = now()->toDateTimeString();

            for ($i = 1; $i <= $totalIn; $i++) {
                $containerNo = sprintf('TEMU%07d', $i);
                $linePartyId = ($i % 5) + 1;
                $block = $blocks[$i % count($blocks)];
                $slotCoord = sprintf('%s-%02d-%02d-%d', $block->code, ($i % 20) + 1, (($i / 20) % 10) + 1, ($i % 4) + 1);

                $gateInPasses[] = [
                    'pass_number' => sprintf('GP-IN-%06d', $i),
                    'container_no' => $containerNo,
                    'pass_type' => 'IN',
                    'vehicle_reg_no' => sprintf('B %04d TX', ($i % 9000) + 1000),
                    'line_party_id' => $linePartyId,
                    'issued_at' => $now,
                    'completed_at' => $now,
                    'status' => 'COMPLETED',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // 1 s.d. 1.000 nantinya di-gate-out, 1.001 s.d. 1.500 tetap IN_YARD
                $isGatedOut = ($i <= $totalOut);

                $yardSlots[] = [
                    'yard_block_id' => $block->id,
                    'container_no' => $containerNo,
                    'slot_coordinate' => $slotCoord,
                    'status' => $isGatedOut ? 'GATED_OUT' : 'IN_YARD',
                    'gate_in_at' => $now,
                    'gate_out_at' => $isGatedOut ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Chunk insert Gate In passes
            foreach (array_chunk($gateInPasses, 500) as $chunk) {
                GatePass::insert($chunk);
            }

            // Chunk insert Yard Slots
            foreach (array_chunk($yardSlots, 500) as $chunk) {
                YardSlot::insert($chunk);
            }

            // Gate Out Passes (1.000)
            $gateOutPasses = [];
            for ($j = 1; $j <= $totalOut; $j++) {
                $containerNo = sprintf('TEMU%07d', $j);
                $linePartyId = ($j % 5) + 1;

                $gateOutPasses[] = [
                    'pass_number' => sprintf('GP-OUT-%06d', $j),
                    'container_no' => $containerNo,
                    'pass_type' => 'OUT',
                    'vehicle_reg_no' => sprintf('B %04d OUT', ($j % 9000) + 1000),
                    'line_party_id' => $linePartyId,
                    'issued_at' => $now,
                    'completed_at' => $now,
                    'status' => 'COMPLETED',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($gateOutPasses, 500) as $chunk) {
                GatePass::insert($chunk);
            }

            // 5. 50 Tagihan Terminal untuk vessel calls
            $terminalBills = [];
            foreach ($vesselCalls as $idx => $call) {
                $thc = 15_000_000;
                $dockage = $call->duration_hours * 50_000;
                $storage = 800_000;
                $dnd = 1_200_000;
                $total = $thc + $dockage + $storage + $dnd;

                $terminalBills[] = [
                    'bill_number' => sprintf('TB-2026-%04d', $idx + 1),
                    'vessel_call_id' => $call->id,
                    'line_party_id' => $call->line_party_id,
                    'amount_idr' => $total,
                    'thc_amount_idr' => $thc,
                    'dockage_amount_idr' => $dockage,
                    'storage_amount_idr' => $storage,
                    'dnd_amount_idr' => $dnd,
                    'ledger_journal_id' => null,
                    'status' => 'ISSUED',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            TerminalBill::insert($terminalBills);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
