<?php

namespace Modules\Agri\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Agri\Domain\Models\AgriColdChainLog;
use Modules\Agri\Domain\Models\AgriCollectionBatch;
use Modules\Agri\Domain\Models\AgriContract;
use Modules\Agri\Domain\Models\AgriFarmer;

class AgriService
{
    public function registerFarmer(array $data): AgriFarmer
    {
        return DB::transaction(function () use ($data) {
            return AgriFarmer::create([
                'farmer_code' => $data['farmer_code'] ?? 'FRM-'.strtoupper(Str::random(6)),
                'farmer_group_name' => $data['farmer_group_name'],
                'full_name' => $data['full_name'],
                'phone_number' => $data['phone_number'],
                'land_polygon_geojson' => $data['land_polygon_geojson'] ?? null,
                'land_area_hectares' => (float) $data['land_area_hectares'],
                'primary_commodity' => $data['primary_commodity'],
                'status' => 'active',
            ]);
        });
    }

    public function createFarmingContract(array $data): AgriContract
    {
        return DB::transaction(function () use ($data) {
            $seedAdvance = (int) ($data['seed_advance_value_idr'] ?? 0);
            $fertAdvance = (int) ($data['fertilizer_advance_value_idr'] ?? 0);
            $totalAdvance = $seedAdvance + $fertAdvance;

            return AgriContract::create([
                'contract_number' => $data['contract_number'] ?? 'AGR-CTR-'.strtoupper(Str::random(8)),
                'farmer_id' => $data['farmer_id'],
                'commodity' => $data['commodity'],
                'planting_date' => $data['planting_date'] ?? now()->toDateString(),
                'expected_harvest_date' => $data['expected_harvest_date'] ?? now()->addMonths(3)->toDateString(),
                'target_yield_kg' => (float) $data['target_yield_kg'],
                'seed_advance_value_idr' => $seedAdvance,
                'fertilizer_advance_value_idr' => $fertAdvance,
                'total_advance_deductible_idr' => $totalAdvance,
                'guaranteed_floor_price_idr_per_kg' => (int) $data['guaranteed_floor_price_idr_per_kg'],
                'status' => 'active',
            ]);
        });
    }

    public function receiveHarvestAtCollectionCenter(array $data): AgriCollectionBatch
    {
        return DB::transaction(function () use ($data) {
            $contract = AgriContract::where('id', $data['contract_id'])->lockForUpdate()->firstOrFail();

            $grossWeight = (float) $data['gross_weight_kg'];
            $grade = $data['grade'] ?? 'GRADE_A';

            // Grade multiplier: Grade A 100%, Grade B 90%, Grade C 80%
            $pricePerKg = match ($grade) {
                'GRADE_A' => $contract->guaranteed_floor_price_idr_per_kg,
                'GRADE_B' => (int) round($contract->guaranteed_floor_price_idr_per_kg * 0.90),
                'GRADE_C' => (int) round($contract->guaranteed_floor_price_idr_per_kg * 0.80),
                default => $contract->guaranteed_floor_price_idr_per_kg,
            };

            $grossPayout = (int) round($grossWeight * $pricePerKg);

            // Deduct advances if available
            $advanceDeduction = 0;
            if ($contract->total_advance_deductible_idr > 0) {
                $advanceDeduction = min($grossPayout, $contract->total_advance_deductible_idr);
                $contract->decrement('total_advance_deductible_idr', $advanceDeduction);
            }

            $netPayout = $grossPayout - $advanceDeduction;

            return AgriCollectionBatch::create([
                'batch_number' => $data['batch_number'] ?? 'BATCH-AGR-'.strtoupper(Str::random(8)),
                'collection_center_id' => $data['collection_center_id'],
                'contract_id' => $contract->id,
                'received_date' => $data['received_date'] ?? now()->toDateString(),
                'gross_weight_kg' => $grossWeight,
                'grade' => $grade,
                'moisture_percentage' => (float) ($data['moisture_percentage'] ?? 14.0),
                'buying_price_per_kg' => $pricePerKg,
                'gross_payout_idr' => $grossPayout,
                'advance_deduction_idr' => $advanceDeduction,
                'net_payout_idr' => $netPayout,
                'payment_status' => 'paid',
                'destination_unit' => $data['destination_unit'] ?? 'RESTO_CK01',
            ]);
        });
    }

    public function recordColdChainReading(array $data): AgriColdChainLog
    {
        return DB::transaction(function () use ($data) {
            $temp = (float) $data['temperature_celsius'];
            // Cold chain safe range for fresh produce: 2C - 8C
            $status = match (true) {
                $temp >= 2.0 && $temp <= 8.0 => 'optimal',
                $temp > 8.0 && $temp <= 12.0 => 'warning',
                default => 'breach',
            };

            return AgriColdChainLog::create([
                'batch_id' => $data['batch_id'],
                'reefer_truck_id' => $data['reefer_truck_id'],
                'recorded_at' => $data['recorded_at'] ?? now(),
                'temperature_celsius' => $temp,
                'humidity_percentage' => (float) ($data['humidity_percentage'] ?? 85.0),
                'gps_coordinates' => $data['gps_coordinates'] ?? null,
                'cold_chain_status' => $status,
            ]);
        });
    }

    public function auditAgri(): array
    {
        $discrepancies = [];

        // Check 1: Payout consistency (gross_payout - advance_deduction == net_payout)
        $batches = AgriCollectionBatch::all();
        foreach ($batches as $batch) {
            $expectedNet = $batch->gross_payout_idr - $batch->advance_deduction_idr;
            if ($batch->net_payout_idr !== $expectedNet) {
                $discrepancies[] = "Batch {$batch->batch_number} payout discrepancy: expected {$expectedNet}, recorded {$batch->net_payout_idr}";
            }
        }

        // Check 2: Advance deduction cannot exceed gross payout
        foreach ($batches as $batch) {
            if ($batch->advance_deduction_idr > $batch->gross_payout_idr) {
                $discrepancies[] = "Batch {$batch->batch_number} advance deduction {$batch->advance_deduction_idr} exceeds gross payout {$batch->gross_payout_idr}";
            }
        }

        return [
            'status' => count($discrepancies) === 0 ? 'HEALTHY' : 'DISCREPANCY',
            'farmers_count' => AgriFarmer::count(),
            'contracts_count' => AgriContract::count(),
            'harvest_batches_count' => $batches->count(),
            'total_harvest_kg' => (float) $batches->sum('gross_weight_kg'),
            'total_net_payout_idr' => (int) $batches->sum('net_payout_idr'),
            'discrepancies' => $discrepancies,
        ];
    }
}
