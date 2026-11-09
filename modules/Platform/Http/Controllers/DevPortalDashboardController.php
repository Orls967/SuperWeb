<?php

declare(strict_types=1);

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Platform\Domain\Models\PhaseMetricSnapshot;
use Modules\Platform\Domain\Models\SandboxEnvironment;
use Modules\Platform\Domain\Models\ServiceSlo;
use Modules\Platform\Http\ViewModels\DevPortalDashboardViewModel;

class DevPortalDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $sandboxes = SandboxEnvironment::where('owner_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        $metrics = PhaseMetricSnapshot::latest()->take(5)->get();
        $slos = ServiceSlo::all();

        $viewModel = new DevPortalDashboardViewModel(
            sandboxes: $sandboxes,
            slos: $slos,
            recentMetrics: $metrics,
            user: $user
        );

        return view('platform::dev.dashboard', [
            'viewModel' => $viewModel,
            'sandboxes' => $sandboxes,
            'metrics' => $metrics,
            'slos' => $slos,
        ]);
    }
}
