<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use Carbon\Carbon;
use Modules\Egy\Domain\Models\ChilledWaterReading;
use Modules\Egy\Domain\Models\DistrictCoolingPlant;
use Modules\Egy\Domain\Models\EscoContract;
use Modules\Egy\Domain\Models\EscoSettlement;
use Modules\Egy\Domain\Models\LeakIncident;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;
use Modules\Egy\Domain\Models\WasteIntake;
use Modules\Egy\Domain\Models\WasteTreatmentFacility;
use Modules\Egy\Domain\Models\WaterNetwork;

final class DistrictUtilitiesManagementService
{
    /**
     * @return array<string, mixed>
     */
    public function getWaterNetworkOverview(): array
    {
        $networks = WaterNetwork::withCount(['nodes', 'leakIncidents'])->get();
        $totalCapacity = (int) $networks->sum('capacity_liters_per_day');
        $activeIncidents = LeakIncident::whereIn('status', ['detected', 'dispatched_work_order'])->count();

        return [
            'networks' => $networks,
            'total_capacity_liters' => $totalCapacity,
            'active_incidents_count' => $activeIncidents,
            'total_dma_count' => $networks->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getWasteManagementOverview(): array
    {
        $facilities = WasteTreatmentFacility::with('intakes')->get();
        $recentIntakes = WasteIntake::with('facility')->latest('recorded_at')->limit(15)->get();
        $totalIntakeKg = (int) WasteIntake::sum('tonnage_kg');
        $totalTippingFee = (int) WasteIntake::sum('total_tipping_fee_idr');
        $totalProjectedBiogas = (int) WasteIntake::sum('projected_biogas_m3');

        return [
            'facilities' => $facilities,
            'recent_intakes' => $recentIntakes,
            'total_intake_tons' => (int) intdiv($totalIntakeKg, 1000),
            'total_tipping_fee_idr' => $totalTippingFee,
            'total_projected_biogas_m3' => $totalProjectedBiogas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDistrictCoolingOverview(): array
    {
        $plants = DistrictCoolingPlant::with('chillers')->get();
        $recentReadings = ChilledWaterReading::with(['plant', 'chiller'])->latest('recorded_at')->limit(15)->get();
        $totalTons = (int) $plants->sum('total_refrigeration_tons');

        $avgCop = (int) ChilledWaterReading::avg('cop_bps') ?: 500;

        return [
            'plants' => $plants,
            'recent_readings' => $recentReadings,
            'total_refrigeration_tons' => $totalTons,
            'average_cop_bps' => $avgCop,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getEscoOverview(): array
    {
        $contracts = EscoContract::with('settlements')->get();
        $settlements = EscoSettlement::with('contract')->latest()->limit(15)->get();
        $totalSavingsIdr = (int) EscoSettlement::sum('total_savings_idr');
        $totalEscoShareIdr = (int) EscoSettlement::sum('esco_share_idr');

        return [
            'contracts' => $contracts,
            'settlements' => $settlements,
            'total_savings_idr' => $totalSavingsIdr,
            'total_esco_share_idr' => $totalEscoShareIdr,
        ];
    }

    public function generateConsolidatedBillsCsv(string $periodMonth): string
    {
        $invoices = UtilityConsolidatedInvoice::where('billing_period', $periodMonth)->get();

        $rows = [];
        $rows[] = 'Invoice Number,Property ID,Customer ID,Billing Period,Total Amount (IDR),Status,Created At';

        foreach ($invoices as $inv) {
            $rows[] = sprintf(
                '"%s",%d,%d,"%s",%d,"%s","%s"',
                $inv->invoice_number,
                $inv->property_id,
                $inv->customer_party_id,
                $inv->billing_period,
                $inv->total_amount_idr,
                $inv->status,
                $inv->created_at?->toIso8601String() ?? Carbon::now()->toIso8601String()
            );
        }

        return implode("\n", $rows);
    }
}
