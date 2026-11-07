<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Domain\Adapters\ApoaSimulationAdapter;
use Modules\Hotel\Domain\Models\HotelApoaReport;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\StateMachines\ApoaReportStateMachine;

final class HotelComplianceController extends Controller
{
    public function apoaQueue(): View
    {
        $properties = HotelProperty::all();
        $reports = HotelApoaReport::with(['reservation', 'property'])
            ->orderBy('deadline_at')
            ->paginate(20);

        return view('hotel::apoa_queue', compact('properties', 'reports'));
    }

    public function submitApoaBatch(Request $request): RedirectResponse
    {
        $pendingReports = HotelApoaReport::where('status', ApoaReportStateMachine::STATUS_PENDING)->get();

        if ($pendingReports->isEmpty()) {
            return back()->with('info', 'Tidak ada laporan APOA pending.');
        }

        $payload = [];
        foreach ($pendingReports as $rep) {
            $payload[] = [
                'apoa_report_id' => (int) $rep->id,
                'passport_blind_index' => $rep->passport_blind_index,
                'nationality' => $rep->nationality_country_code,
            ];
        }

        $batch = ApoaSimulationAdapter::submitBatch($payload);

        foreach ($pendingReports as $rep) {
            $rep->update([
                'status' => ApoaReportStateMachine::STATUS_SUBMITTED,
                'apoa_submission_batch' => $batch['batch_id'],
                'submitted_at' => CarbonImmutable::now(),
            ]);
        }

        return back()->with('success', "Batch {$batch['batch_id']} ({$pendingReports->count()} tamu WNA) berhasil dikirim ke Imigrasi.");
    }
}
