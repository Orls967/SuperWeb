<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AgriAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'agri:audit';
    }

    public function corrupt(): string
    {
        $farmerId = (string) Str::uuid();
        DB::table('agri_farmers')->insert([
            'id' => $farmerId,
            'farmer_code' => 'FARM-'.Str::random(4),
            'farmer_group_name' => 'Kelompok Tani 1',
            'full_name' => 'Petani Uji',
            'phone_number' => '0812345678',
            'land_area_hectares' => 1.5,
            'primary_commodity' => 'PADI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contractId = (string) Str::uuid();
        DB::table('agri_contracts')->insert([
            'id' => $contractId,
            'contract_number' => 'CTR-AGRI-'.Str::random(4),
            'farmer_id' => $farmerId,
            'commodity' => 'PADI',
            'planting_date' => now()->toDateString(),
            'expected_harvest_date' => now()->addDays(90)->toDateString(),
            'target_yield_kg' => 1000,
            'guaranteed_floor_price_idr_per_kg' => 10000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('agri_collection_batches')->insert([
            'id' => (string) Str::uuid(),
            'batch_number' => 'BATCH-CORRUPT-'.Str::random(5),
            'collection_center_id' => 'CC-01',
            'contract_id' => $contractId,
            'received_date' => now()->toDateString(),
            'gross_weight_kg' => 100.0,
            'grade' => 'A',
            'moisture_percentage' => 12.0,
            'buying_price_per_kg' => 10000,
            'gross_payout_idr' => 1000000,
            'advance_deduction_idr' => 100000,
            'net_payout_idr' => 99999999, // Seharusnya 900.000
            'payment_status' => 'paid',
            'destination_unit' => 'CK-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'payout discrepancy';
    }
}
