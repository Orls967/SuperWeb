<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Mining\Domain\Models\MiningFuelAnomalyCase;
use Modules\Mining\Domain\Models\MiningHaulCycle;
use Modules\Mining\Domain\Models\MiningPlanShift;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MiningReportController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        $reportType = $request->query('type', 'operations');

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"mining_{$reportType}_report_".date('Ymd_His').'.csv"',
        ];

        return response()->stream(function () use ($reportType) {
            $handle = fopen('php://output', 'w');

            if ($reportType === 'fuel') {
                fputcsv($handle, ['Nomor Kasus', 'Site ID', 'Kontraktor', 'Equipment Code', 'Tanggal Shift', 'BBM Aktual (L)', 'BBM Garis Dasar (L)', 'Deviasi (Bps)', 'Dana Ditahan (IDR)', 'Status']);
                $cases = MiningFuelAnomalyCase::with('equipment')->latest()->get();
                foreach ($cases as $c) {
                    fputcsv($handle, [
                        $c->case_number,
                        $c->site_id,
                        $c->contractor_party_id,
                        $c->equipment?->equipment_code ?? '-',
                        $c->shift_date?->format('Y-m-d') ?? '-',
                        round($c->actual_fuel_ml / 1000, 2),
                        round($c->baseline_fuel_ml / 1000, 2),
                        $c->excess_bps,
                        $c->held_amount_idr,
                        $c->status instanceof \BackedEnum ? $c->status->value : (string) $c->status,
                    ]);
                }
            } else {
                fputcsv($handle, ['Tanggal', 'Shift', 'Pit ID', 'Target Tonase (Kg)', 'Realisasi Tonase (Kg)', 'Jam Kerja Nominal', 'Jam Kerja Disesuaikan', 'Downtime Hujan', 'Downtime Servis', 'Varians (Kg)']);
                $shifts = MiningPlanShift::latest()->get();
                foreach ($shifts as $s) {
                    fputcsv($handle, [
                        $s->shift_date?->format('Y-m-d') ?? '-',
                        $s->shift_code,
                        $s->pit_id,
                        $s->target_tonnage_kg,
                        $s->actual_tonnage_kg,
                        $s->effective_hours_nominal,
                        $s->effective_hours_adjusted,
                        $s->rain_downtime_hours,
                        $s->maintenance_downtime_hours,
                        $s->variance_kg,
                    ]);
                }
            }

            fclose($handle);
        }, 200, $headers);
    }
}
