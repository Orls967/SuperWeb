<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Admin\Spine;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Core\Application\Actions\EventSpine\ReplaySpineStreamAction;
use Modules\Core\Application\Services\SpineLagMonitorService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpineConsumerController extends Controller
{
    public function __construct(
        protected SpineLagMonitorService $lagMonitor,
        protected ReplaySpineStreamAction $replayAction
    ) {}

    public function index(): View
    {
        $metrics = $this->lagMonitor->getConsumerGroupMetrics();
        $overallP95Lag = $this->lagMonitor->getOverallP95Lag();

        return view('core::admin.spine.consumers', compact('metrics', 'overallP95Lag'));
    }

    public function replayRange(Request $request): RedirectResponse
    {
        $group = (string) $request->input('consumer_group');
        $topic = (string) $request->input('topic', '*');
        $from = (int) $request->input('from_offset', 0);
        $to = $request->filled('to_offset') ? (int) $request->input('to_offset') : null;

        $res = $this->replayAction->execute($group, $topic, $from, $to);

        return back()->with('status', "Replay berhasil untuk grup [{$group}]: {$res['events_count']} event diproses. Hash proyeksi kanonikal: {$res['projection_hash']}");
    }

    public function exportCsv(): StreamedResponse
    {
        $metrics = $this->lagMonitor->getConsumerGroupMetrics();

        return response()->streamDownload(function () use ($metrics) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['consumer_group', 'topic', 'last_offset', 'max_offset', 'current_lag', 'updated_at']);
            foreach ($metrics as $m) {
                fputcsv($handle, [
                    $m['consumer_group'],
                    $m['topic'],
                    $m['last_offset'],
                    $m['max_offset'],
                    $m['current_lag'],
                    $m['updated_at'] ?? '',
                ]);
            }
            fclose($handle);
        }, 'consumer_lag_export_'.date('Ymd_His').'.csv');
    }
}
