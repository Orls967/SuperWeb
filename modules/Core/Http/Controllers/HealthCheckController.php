<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Services\SystemHealthService;
use Modules\Core\Domain\Models\AuditLog;

class HealthCheckController extends Controller
{
    public function index(Request $request, SystemHealthService $healthService): View
    {
        $health = $healthService->check($request->user());

        $recentAuditLogs = AuditLog::with('user')
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get();

        return view('core::admin.health', [
            'health' => $health,
            'recentAuditLogs' => $recentAuditLogs,
        ]);
    }

    public function run(Request $request, SystemHealthService $healthService): RedirectResponse
    {
        $result = $healthService->check($request->user());

        $msg = $result['status'] === 'HEALTHY'
            ? 'Diagnosa selesai: Seluruh sub-sistem beroperasi normal (HEALTHY).'
            : 'Diagnosa selesai: Ditemukan sub-sistem yang memerlukan perhatian.';

        return redirect()->route('admin.health.index')->with(
            $result['status'] === 'HEALTHY' ? 'success' : 'error',
            $msg
        );
    }
}
