<?php

declare(strict_types=1);

namespace Modules\Esg\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Esg\Contracts\EmissionBenefitRecorder;

class EmissionBenefitRecorderService implements EmissionBenefitRecorder
{
    public function recordAvoidedEmission(
        string $sourceType,
        int $sourceId,
        string $materialCode,
        int $quantityGrams,
        CarbonImmutable $at,
        string $idempotencyKey
    ): int {
        return DB::transaction(function () use (
            $sourceType,
            $sourceId,
            $materialCode,
            $quantityGrams,
            $at,
            $idempotencyKey
        ): int {
            $existing = DB::table('esg_avoided_emissions')
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return (int) $existing->avoided_co2e_grams;
            }

            $atString = $at->toDateTimeString();

            // Ambil faktor emisi berversi (D79-4, S1)
            $factorRow = DB::table('esg_circular_factors')
                ->where('material_code', $materialCode)
                ->where('effective_from', '<=', $atString)
                ->where(function ($q) use ($atString) {
                    $q->whereNull('effective_to')
                        ->orWhere('effective_to', '>', $atString);
                })
                ->orderByDesc('effective_from')
                ->first();

            // Default fallback bila belum terdaftar: 2100 g CO2e per kg (2.1 kg CO2e / kg)
            $factorGPerKg = $factorRow ? (int) $factorRow->co2e_factor_g_per_kg : 2100;

            // gram CO2e = round_half_up(gram * faktor / 1.000)
            $avoidedCo2eGrams = (int) round(($quantityGrams * $factorGPerKg) / 1000, 0, PHP_ROUND_HALF_UP);

            DB::table('esg_avoided_emissions')->insert([
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'material_code' => $materialCode,
                'quantity_grams' => $quantityGrams,
                'avoided_co2e_grams' => $avoidedCo2eGrams,
                'idempotency_key' => $idempotencyKey,
                'recorded_at' => $at,
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            return $avoidedCo2eGrams;
        });
    }
}
