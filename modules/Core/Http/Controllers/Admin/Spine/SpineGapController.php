<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Admin\Spine;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Core\Application\Actions\EventSpine\ScanAggregateGapsAction;
use Modules\Core\Domain\Models\CoreSpineGap;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpineGapController extends Controller
{
    public function __construct(
        protected ScanAggregateGapsAction $gapScanAction
    ) {}

    public function index(Request $request): View
    {
        $status = $request->get('status', 'all');
        $query = CoreSpineGap::query();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $gaps = $query->orderBy('detected_at', 'desc')->paginate(25);
        $openCount = CoreSpineGap::where('status', CoreSpineGap::STATUS_OPEN)->count();
        $resolvedCount = CoreSpineGap::where('status', CoreSpineGap::STATUS_RESOLVED)->count();

        return view('core::admin.spine.gaps', compact('gaps', 'status', 'openCount', 'resolvedCount'));
    }

    public function scan(Request $request): RedirectResponse
    {
        $aggType = $request->input('aggregate_type');
        $aggId = $request->input('aggregate_id');

        $res = $this->gapScanAction->execute($aggType, $aggId);

        return back()->with('status', "Pemindaian celah selesai: {$res['gaps_found']} celah baru ditemukan, {$res['gaps_resolved']} celah tertutup.");
    }

    public function resolve(Request $request, ?int $id = null): RedirectResponse
    {
        $gapId = $id ?? (int) $request->input('id');
        $gap = CoreSpineGap::find($gapId);
        if ($gap) {
            $gap->status = CoreSpineGap::STATUS_RESOLVED;
            $gap->resolved_at = now();
            $gap->save();

            return back()->with('status', "Celah #{$gapId} ditandai selesai secara manual.");
        }

        return back()->with('error', "Celah #{$gapId} tidak ditemukan.");
    }

    public function exportCsv(): StreamedResponse
    {
        $gaps = CoreSpineGap::all();

        return response()->streamDownload(function () use ($gaps) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'aggregate_type', 'aggregate_id', 'gap_start', 'gap_end', 'status', 'detected_at', 'resolved_at']);
            foreach ($gaps as $g) {
                fputcsv($handle, [
                    $g->id,
                    $g->aggregate_type,
                    $g->aggregate_id,
                    $g->gap_start,
                    $g->gap_end,
                    $g->status,
                    (string) $g->detected_at,
                    (string) $g->resolved_at,
                ]);
            }
            fclose($handle);
        }, 'spine_gaps_export_'.date('Ymd_His').'.csv');
    }
}
