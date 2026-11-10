<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Modules\Core\Domain\Models\CoreSearchIndex;
use Modules\Core\Domain\Models\CoreSearchIndexJob;
use Modules\Core\Domain\Models\CoreSearchQueryLog;
use Modules\Core\Http\Requests\TriggerReindexRequest;

class SearchAnalyticsController extends Controller
{
    public function analytics(Request $request): View
    {
        $days = (int) $request->input('days', 7);
        $since = now()->subDays($days);

        $totalQueries = CoreSearchQueryLog::where('created_at', '>=', $since)->count();
        $zeroResultQueries = CoreSearchQueryLog::where('created_at', '>=', $since)->where('results_count', 0)->count();
        $avgLatency = (float) (CoreSearchQueryLog::where('created_at', '>=', $since)->avg('execution_time_ms') ?: 0.0);

        $topQueries = CoreSearchQueryLog::where('created_at', '>=', $since)
            ->selectRaw('redacted_query, count(*) as count, avg(results_count) as avg_results, avg(execution_time_ms) as latency')
            ->groupBy('redacted_query')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $zeroResults = CoreSearchQueryLog::where('created_at', '>=', $since)
            ->where('results_count', 0)
            ->selectRaw('redacted_query, count(*) as count, max(created_at) as last_seen')
            ->groupBy('redacted_query')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $totalDocuments = CoreSearchIndex::count();
        $modulesDistribution = CoreSearchIndex::selectRaw('module, count(*) as total')
            ->groupBy('module')
            ->pluck('total', 'module')
            ->toArray();

        $recentJobs = CoreSearchIndexJob::orderByDesc('created_at')->limit(10)->get();

        return view('core::search.analytics', [
            'days' => $days,
            'totalQueries' => $totalQueries,
            'zeroResultQueries' => $zeroResultQueries,
            'avgLatency' => round($avgLatency, 2),
            'topQueries' => $topQueries,
            'zeroResults' => $zeroResults,
            'totalDocuments' => $totalDocuments,
            'modulesDistribution' => $modulesDistribution,
            'recentJobs' => $recentJobs,
        ]);
    }

    public function diagnostics(Request $request): View
    {
        return view('core::search.diagnostics');
    }

    public function triggerReindex(TriggerReindexRequest $request): RedirectResponse
    {
        $module = $request->input('module');
        $params = [];
        if (! empty($module)) {
            $params['--module'] = $module;
        }

        Artisan::call('search:reindex', $params);

        return redirect()->route('core.search.analytics')->with('success', 'Pekerjaan reindexing berhasil dipicu.');
    }
}
