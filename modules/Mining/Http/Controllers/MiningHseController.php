<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Mining\Application\Actions\AuditMiningConcessionPermitsAction;
use Modules\Mining\Application\Actions\EvaluateBlastingVibrationAction;
use Modules\Mining\Application\Actions\IssueHighRiskWorkPermitAction;
use Modules\Mining\Application\Actions\ScanExpiringPermitsAction;
use Modules\Mining\Domain\Models\MiningWorkPermit;

class MiningHseController extends Controller
{
    public function index()
    {
        $permits = MiningWorkPermit::latest()->take(20)->get();

        return view('mining::hse', [
            'permits' => $permits,
        ]);
    }

    /**
     * Penerbitan izin kerja K3 (PTW) risiko tinggi (D94-5).
     */
    public function issuePermit(Request $request, IssueHighRiskWorkPermitAction $action)
    {
        $validated = $request->validate([
            'site_id' => 'required|uuid',
            'worker_id' => 'required|string|max:40',
            'activity_type' => 'required|string|max:32',
            'geofence_zone' => 'required|string|max:40',
            'risk_level' => 'required|string|in:low,medium,high,critical',
            'valid_hours' => 'required|integer|between:1,24',
            'supervisor_name' => 'required|string|max:80',
        ]);

        $permit = $action->execute($validated);

        return redirect()->back()->with('success', "Izin kerja K3 #{$permit->permit_number} berhasil diterbitkan.");
    }

    /**
     * Pemindaian kedaluwarsa izin kerja K3 lapangan (D94-5).
     */
    public function scanExpiry(ScanExpiringPermitsAction $action)
    {
        $result = $action->execute();

        return redirect()->back()->with('success', "Pemindaian selesai: {$result['expired_count']} izin kerja kedaluwarsa dihentikan.");
    }

    /**
     * Audit kepatuhan seluruh izin konsesi pertambangan (D94-5, D94-6).
     */
    public function auditPermits(Request $request, AuditMiningConcessionPermitsAction $action)
    {
        $siteId = (string) $request->input('site_id');
        $result = $action->execute($siteId);

        return response()->json($result);
    }

    /**
     * Evaluasi getaran peledakan SNI 7571:2010 & airblast (D94-5).
     */
    public function evaluateBlasting(Request $request, EvaluateBlastingVibrationAction $action)
    {
        $validated = $request->validate([
            'site_id' => 'required|uuid',
            'blast_event_id' => 'required|string|max:40',
            'distance_meters' => 'required|numeric|min:1',
            'charge_per_delay_kg' => 'required|numeric|min:0.1',
            'dominant_frequency_hz' => 'nullable|numeric|min:1',
            'structure_class' => 'nullable|string|in:HERITAGE,RESIDENTIAL,INDUSTRIAL',
        ]);

        $result = $action->execute($validated);

        return redirect()->back()->with('success', "Evaluasi getaran {$result['blast_event_id']}: {$result['overall_safety_status']}");
    }
}
