<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Insurance\Application\Actions\AggregateCommandCenterMetricsAction;
use Modules\Insurance\Application\Actions\ResolveRegulatoryGrievanceAction;
use Modules\Insurance\Application\Actions\SubmitRegulatoryGrievanceAction;
use Modules\Insurance\Application\Services\AntiMoneyLaunderingInsuranceService;
use Modules\Insurance\Application\Services\InsuranceComplianceCalendarService;
use Modules\Insurance\Application\Services\InsuranceExecutiveAnalyticsService;
use Modules\Insurance\Domain\Models\InsuranceCommandSnapshot;
use Modules\Insurance\Domain\Models\InsuranceGrievanceTicket;

final class InsuranceCommandCenterController extends Controller
{
    public function dashboard(): View
    {
        $today = CarbonImmutable::now()->toDateString();
        $snapshot = InsuranceCommandSnapshot::where('snapshot_date', $today)->first();
        if ($snapshot === null) {
            $snapshot = app(AggregateCommandCenterMetricsAction::class)->execute($today);
        }

        $recentSnapshots = InsuranceCommandSnapshot::latest('snapshot_date')->take(7)->get();

        return view('insurance::command.dashboard', compact('snapshot', 'recentSnapshots'));
    }

    public function aml(AntiMoneyLaunderingInsuranceService $amlService): View
    {
        $report = $amlService->scanSuspiciousTransactions();

        return view('insurance::command.aml', compact('report'));
    }

    public function complianceCalendar(InsuranceComplianceCalendarService $calendarService): View
    {
        $year = (int) CarbonImmutable::now()->year;
        $calendar = $calendarService->evaluateComplianceSchedule($year);

        return view('insurance::command.compliance_calendar', compact('calendar'));
    }

    public function stressTest(InsuranceExecutiveAnalyticsService $analyticsService): View
    {
        $simulation = $analyticsService->runRbcStressTestSimulation();

        return view('insurance::command.stress_test', compact('simulation'));
    }

    public function snapshot(Request $request, AggregateCommandCenterMetricsAction $action): JsonResponse
    {
        $date = $request->input('date', CarbonImmutable::now()->toDateString());
        $snapshot = $action->execute($date);

        return response()->json([
            'status' => 'success',
            'message' => 'Snapshot metrik command center asuransi berhasil dikompilasi.',
            'snapshot' => $snapshot,
        ]);
    }

    public function regulatory(): View
    {
        $grievances = InsuranceGrievanceTicket::latest()->paginate(20);

        return view('insurance::command.regulatory', compact('grievances'));
    }

    public function grievance(Request $request, SubmitRegulatoryGrievanceAction $action): JsonResponse
    {
        $validated = $request->validate([
            'ticket_number' => 'required|string|max:60|unique:ins_compliance_grievances,ticket_number',
            'policy_ref' => 'required|string|max:60',
            'complainant_party_id' => 'required|uuid',
            'category' => 'required|string|in:CLAIM_DENIAL,DELAYED_PAYOUT,PREMIUM_DISPUTE',
            'grievance_text' => 'required|string|max:1000',
            'sla_days' => 'nullable|integer|min:3|max:30',
        ]);

        $actorId = (string) ($request->user()?->id ?? 1);
        $idempotencyKey = $request->header('X-Idempotency-Key') ?? ('grievance:'.$validated['ticket_number']);

        $ticket = $action->execute(
            ticketNumber: $validated['ticket_number'],
            policyRef: $validated['policy_ref'],
            complainantPartyId: $validated['complainant_party_id'],
            category: $validated['category'],
            grievanceText: $validated['grievance_text'],
            slaDays: (int) ($validated['sla_days'] ?? 14),
            actorId: $actorId,
            idempotencyKey: $idempotencyKey,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Tiket kepatuhan regulasi pengaduan nasabah berhasil dicatat.',
            'ticket' => $ticket,
        ], 201);
    }

    public function resolveGrievance(Request $request, ResolveRegulatoryGrievanceAction $action): JsonResponse
    {
        $validated = $request->validate([
            'ticket_number' => 'required|string|exists:ins_compliance_grievances,ticket_number',
            'resolution_status' => 'required|string|in:resolved,escalated_ojk',
            'resolution_summary' => 'required|string|max:1000',
        ]);

        $actorId = (string) ($request->user()?->id ?? 1);
        $idempotencyKey = $request->header('X-Idempotency-Key') ?? ('resolve:'.$validated['ticket_number']);

        $ticket = $action->execute(
            ticketNumber: $validated['ticket_number'],
            resolutionStatus: $validated['resolution_status'],
            resolutionSummary: $validated['resolution_summary'],
            actorId: $actorId,
            idempotencyKey: $idempotencyKey,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Penyelesaian pengaduan regulasi berhasil diproses.',
            'ticket' => $ticket,
        ]);
    }
}
