<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\EnterpriseFinance\Application\Services\SodGovernanceService;
use Modules\EnterpriseFinance\Domain\Models\SodException;

class SodGovernanceController extends Controller
{
    public function __construct(
        protected readonly SodGovernanceService $sodService
    ) {}

    public function index(Request $request): View
    {
        $rules = $this->sodService->getRules();
        $exceptions = SodException::query()
            ->orderBy('id', 'desc')
            ->paginate(15);

        return view('enterprise_finance::sod_governance', [
            'rules' => $rules,
            'exceptions' => $exceptions,
        ]);
    }

    public function grantException(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sod_rule_id' => ['required', 'string'],
            'user_id' => ['required', 'integer'],
            'justification' => ['required', 'string', 'min:5'],
            'expires_days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $this->sodService->grantException(
            ruleId: $validated['sod_rule_id'],
            userId: (int) $validated['user_id'],
            grantedBy: (int) ($request->user()?->id ?? 1),
            justification: $validated['justification'],
            expiresAt: CarbonImmutable::now()->addDays((int) $validated['expires_days'])
        );

        return redirect()->route('enterprise_finance.sod')
            ->with('status', 'Dispensasi pemisahan tugas berhasil diterbitkan.');
    }
}
