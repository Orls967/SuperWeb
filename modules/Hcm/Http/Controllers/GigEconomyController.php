<?php

declare(strict_types=1);

namespace Modules\Hcm\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hcm\Application\Services\Gig\InternalGigEconomyService;
use Modules\Hcm\Domain\Models\Gig\GovBounty;
use Modules\Hcm\Domain\Models\Gig\GovBountyClaim;
use Modules\Hcm\Domain\Models\Gig\GovBountyPof;
use Throwable;

class GigEconomyController extends Controller
{
    public function __construct(
        private readonly InternalGigEconomyService $service
    ) {}

    public function index(): View
    {
        return view('hcm::hcm.bounties', [
            'bounties' => GovBounty::orderByDesc('created_at')->limit(50)->get(),
            'claims' => GovBountyClaim::with(['bounty', 'pof'])->orderByDesc('created_at')->limit(50)->get(),
            'pofs' => GovBountyPof::with('claim.bounty')->orderByDesc('created_at')->limit(50)->get(),
            'summary' => $this->service->getBountyMarketplaceSummary(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_unit_cost_center' => 'required|string|max:64',
            'title' => 'required|string|max:128',
            'description' => 'required|string',
            'shift_start' => 'required|date',
            'shift_end' => 'required|date|after:shift_start',
            'hourly_rate_idr' => 'required|integer|min:1000',
            'required_certification' => 'nullable|string|max:64',
            'requires_high_risk_permit' => 'nullable|boolean',
            'speed_bonus_idr' => 'nullable|integer|min:0',
        ]);

        try {
            $bounty = $this->service->postBounty(
                costCenter: $data['business_unit_cost_center'],
                title: $data['title'],
                description: $data['description'],
                shiftStart: Carbon::parse($data['shift_start']),
                shiftEnd: Carbon::parse($data['shift_end']),
                hourlyRateIdr: (int) $data['hourly_rate_idr'],
                requiredCertification: $data['required_certification'] ?? null,
                requiresHighRiskPermit: (bool) ($data['requires_high_risk_permit'] ?? false),
                speedBonusIdr: (int) ($data['speed_bonus_idr'] ?? 0)
            );

            return back()->with('success', "Bounty gig {$bounty->bounty_code} berhasil dipublikasikan ke talent marketplace.");
        } catch (Throwable $e) {
            return back()->withErrors(['bounty' => $e->getMessage()]);
        }
    }

    public function claim(Request $request, GovBounty $bounty): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|integer',
            'certifications' => 'nullable|string',
            'already_worked_hours_today' => 'nullable|numeric|min:0|max:24',
            'already_worked_overtime_this_week' => 'nullable|numeric|min:0|max:100',
            'work_permit_code' => 'nullable|string|max:64',
        ]);

        try {
            $certs = ! empty($data['certifications'])
                ? array_map('trim', explode(',', $data['certifications']))
                : [];

            $claim = $this->service->claimBounty(
                bounty: $bounty,
                employeeId: (int) $data['employee_id'],
                employeeCertifications: $certs,
                alreadyWorkedHoursToday: (float) ($data['already_worked_hours_today'] ?? 0.0),
                alreadyWorkedOvertimeThisWeek: (float) ($data['already_worked_overtime_this_week'] ?? 0.0),
                workPermitCode: $data['work_permit_code'] ?? null
            );

            return back()->with('success', "Bounty {$bounty->bounty_code} berhasil diklaim ({$claim->claim_code}). Poin mobilitas internal +{$claim->mobility_points_earned}.");
        } catch (Throwable $e) {
            return back()->withErrors(['claim' => $e->getMessage()]);
        }
    }

    public function payout(Request $request, GovBountyClaim $claim): RedirectResponse
    {
        $data = $request->validate([
            'actual_hours_worked' => 'required|numeric|min:0.5|max:24',
            'geo_location_hash' => 'required|string|max:64',
            'photo_evidence_ref' => 'nullable|string|max:128',
        ]);

        try {
            $supervisorUserId = (int) $request->user()->id;

            $pof = $this->service->approveProofOfWorkAndPayout(
                claim: $claim,
                actualHoursWorked: (float) $data['actual_hours_worked'],
                geoLocationHash: $data['geo_location_hash'],
                supervisorUserId: $supervisorUserId,
                photoEvidenceRef: $data['photo_evidence_ref'] ?? null
            );

            return back()->with('success', "POF {$pof->pof_code} disetujui! Upah lembur Rp ".number_format($pof->calculated_overtime_pay_idr).' berhasil diposting ke dompet karyawan via Core Banking.');
        } catch (Throwable $e) {
            return back()->withErrors(['payout' => $e->getMessage()]);
        }
    }
}
