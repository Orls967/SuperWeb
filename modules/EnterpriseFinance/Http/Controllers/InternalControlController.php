<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\EnterpriseFinance\Application\Services\InternalControlTestingService;
use Modules\EnterpriseFinance\Domain\Models\ControlPoint;
use Modules\EnterpriseFinance\Domain\Models\ControlTestRun;

class InternalControlController extends Controller
{
    public function __construct(
        protected readonly InternalControlTestingService $controlService
    ) {}

    public function index(Request $request): View
    {
        $this->controlService->ensureControlPointsExist();

        $points = ControlPoint::all();
        $testRuns = ControlTestRun::query()
            ->with('controlPoint')
            ->orderBy('tested_at', 'desc')
            ->paginate(15);

        return view('enterprise_finance::internal_controls', [
            'points' => $points,
            'testRuns' => $testRuns,
        ]);
    }

    public function runTests(Request $request): RedirectResponse
    {
        $this->controlService->runDailyControlTests(50);

        return redirect()->route('enterprise_finance.controls')
            ->with('status', 'Pengujian kontrol internal (RCM ICFR) harian berhasil dijalankan.');
    }
}
