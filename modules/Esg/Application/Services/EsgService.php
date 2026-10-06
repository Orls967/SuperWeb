<?php

namespace Modules\Esg\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Esg\Domain\Models\CarbonCredit;
use Modules\Esg\Domain\Models\EsgEmission;
use Modules\Esg\Domain\Models\OffsetRetirement;
use Modules\Esg\Domain\Models\SupplierScore;

class EsgService
{
    /**
     * Standard emission factors (kg CO2e per unit):
     * - fuel_diesel: 2.68 kg / liter
     * - fuel_gasoline: 2.31 kg / liter
     * - electricity_grid: 0.79 kg / kWh (Jawa-Madura-Bali grid average)
     * - freight_road: 0.12 kg / ton-km
     */
    public function recordEmission(array $data): EsgEmission
    {
        return DB::transaction(function () use ($data) {
            $amount = (float) $data['activity_data_amount'];
            $factor = (float) ($data['emission_factor'] ?? match ($data['activity_type']) {
                'fuel_diesel' => 2.68,
                'fuel_gasoline' => 2.31,
                'electricity_grid' => 0.79,
                'freight_road' => 0.12,
                default => 1.0,
            });

            $co2eKg = round($amount * $factor, 4);

            return EsgEmission::create([
                'emission_number' => $data['emission_number'] ?? 'EM-'.strtoupper(Str::random(8)),
                'entity_id' => $data['entity_id'],
                'scope' => $data['scope'],
                'activity_type' => $data['activity_type'],
                'activity_data_amount' => $amount,
                'activity_uom' => $data['activity_uom'],
                'emission_factor' => $factor,
                'co2e_kg' => $co2eKg,
                'reporting_period' => $data['reporting_period'] ?? now()->toDateString(),
                'source_module' => $data['source_module'] ?? null,
                'source_reference' => $data['source_reference'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);
        });
    }

    public function registerCarbonCredit(array $data): CarbonCredit
    {
        return DB::transaction(function () use ($data) {
            $tons = (float) $data['quantity_co2e_tons'];
            $costPerTon = (float) ($data['cost_per_ton_idr'] ?? 150000);
            $totalCost = (int) round($tons * $costPerTon);

            return CarbonCredit::create([
                'certificate_number' => $data['certificate_number'] ?? 'CC-'.strtoupper(Str::random(10)),
                'registry' => $data['registry'] ?? 'IDX_CARBON',
                'project_name' => $data['project_name'],
                'project_type' => $data['project_type'] ?? 'reforestation',
                'vintage_year' => $data['vintage_year'] ?? (int) date('Y'),
                'quantity_co2e_tons' => $tons,
                'cost_per_ton_idr' => $costPerTon,
                'total_cost_idr' => $totalCost,
                'status' => 'active',
            ]);
        });
    }

    public function retireOffset(string $carbonCreditId, array $data): OffsetRetirement
    {
        return DB::transaction(function () use ($carbonCreditId, $data) {
            $credit = CarbonCredit::where('id', $carbonCreditId)->lockForUpdate()->firstOrFail();
            $retiredQty = (float) $data['retired_quantity_tons'];

            $alreadyRetired = (float) $credit->retirements()->sum('retired_quantity_tons');
            if (($alreadyRetired + $retiredQty) > (float) $credit->quantity_co2e_tons) {
                throw new \InvalidArgumentException('Retirement exceeds available carbon credit balance.');
            }

            $retirement = OffsetRetirement::create([
                'retirement_number' => $data['retirement_number'] ?? 'RET-'.strtoupper(Str::random(8)),
                'carbon_credit_id' => $credit->id,
                'entity_id' => $data['entity_id'],
                'retired_quantity_tons' => $retiredQty,
                'reason' => $data['reason'] ?? 'Annual GHG Scope 1 & 2 Neutralization',
                'retired_at' => $data['retired_at'] ?? now()->toDateString(),
                'certificate_url' => $data['certificate_url'] ?? null,
            ]);

            if (($alreadyRetired + $retiredQty) >= (float) $credit->quantity_co2e_tons) {
                $credit->update(['status' => 'retired']);
            }

            return $retirement;
        });
    }

    public function evaluateSupplierSustainability(array $data): SupplierScore
    {
        return DB::transaction(function () use ($data) {
            $env = (int) $data['environmental_score'];
            $soc = (int) $data['social_score'];
            $gov = (int) $data['governance_score'];

            // Weighted: Env 40%, Soc 30%, Gov 30%
            $overall = round(($env * 0.40) + ($soc * 0.30) + ($gov * 0.30), 2);

            $level = match (true) {
                $overall >= 85 => 'LEAD',
                $overall >= 70 => 'ADVANCED',
                $overall >= 50 => 'COMPLIANT',
                default => 'HIGH_RISK',
            };

            return SupplierScore::updateOrCreate(
                [
                    'supplier_id' => $data['supplier_id'],
                    'evaluation_year' => $data['evaluation_year'] ?? date('Y'),
                ],
                [
                    'environmental_score' => $env,
                    'social_score' => $soc,
                    'governance_score' => $gov,
                    'overall_score' => $overall,
                    'certification_list' => $data['certification_list'] ?? null,
                    'rating_level' => $level,
                    'audit_notes' => $data['audit_notes'] ?? 'GRI and ISO standard assessment.',
                ]
            );
        });
    }

    public function auditEsg(): array
    {
        $discrepancies = [];

        // Check 1: Emission calculations consistency
        $emissions = EsgEmission::all();
        foreach ($emissions as $em) {
            $expected = round((float) $em->activity_data_amount * (float) $em->emission_factor, 4);
            if (abs($expected - (float) $em->co2e_kg) > 0.01) {
                $discrepancies[] = "Emission calculation mismatch for {$em->emission_number}: expected {$expected}, got {$em->co2e_kg}";
            }
        }

        // Check 2: Offset retirements do not exceed carbon credits
        $credits = CarbonCredit::with('retirements')->get();
        foreach ($credits as $credit) {
            $sumRetired = (float) $credit->retirements->sum('retired_quantity_tons');
            if ($sumRetired > (float) $credit->quantity_co2e_tons + 0.0001) {
                $discrepancies[] = "Credit {$credit->certificate_number} over-retired: total {$credit->quantity_co2e_tons}, retired {$sumRetired}";
            }
        }

        return [
            'status' => count($discrepancies) === 0 ? 'HEALTHY' : 'DISCREPANCY',
            'emissions_count' => $emissions->count(),
            'total_co2e_tons' => round($emissions->sum('co2e_kg') / 1000, 4),
            'credits_count' => $credits->count(),
            'total_offset_tons' => (float) OffsetRetirement::sum('retired_quantity_tons'),
            'discrepancies' => $discrepancies,
        ];
    }
}
