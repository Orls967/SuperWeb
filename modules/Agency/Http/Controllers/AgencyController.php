<?php

declare(strict_types=1);

namespace Modules\Agency\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Agency\Application\Services\AgencyService;
use Modules\Agency\Domain\Models\Agent;
use Modules\Agency\Domain\Models\Attribution;
use Modules\Agency\Domain\Models\CommissionAccrual;
use Modules\Agency\Domain\Models\CommissionScheme;
use Modules\Agency\Domain\Models\Payout;

class AgencyController extends Controller
{
    public function __construct(private readonly AgencyService $service) {}

    public function index(): View
    {
        return view('agency::index', [
            'agents' => Agent::with(['parent', 'children'])->orderBy('code')->get(),
            'schemes' => CommissionScheme::with('agent')->orderBy('agent_id')->limit(50)->get(),
            'accruals' => CommissionAccrual::with('agent')->orderBy('hold_until')->limit(50)->get(),
            'payouts' => Payout::with('agent')->orderByDesc('period')->limit(30)->get(),
            'attributions' => Attribution::with('agent')->orderBy('touched_at')->limit(30)->get(),
        ]);
    }

    public function show(Agent $agent): View
    {
        $agent->load(['parent', 'children', 'contracts', 'schemes', 'accruals', 'payouts']);

        return view('agency::show', [
            'agent' => $agent,
            'statement' => $this->service->buildStatement($agent, now()->format('Y')),
        ]);
    }

    public function storeAgent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:40', 'name' => 'required|string|max:180',
            'kind' => 'required|in:sales_agent,broker,reseller,affiliate,sole_agent',
            'parent_id' => 'nullable|uuid|exists:agy_agents,id',
            'party_id' => 'nullable|uuid|exists:pty_parties,id',
            'region_code' => 'nullable|string|max:40',
        ]);
        $this->service->registerAgent($data);

        return back()->with('success', 'Agen terdaftar (onboarding).');
    }

    public function transition(Agent $agent, Request $request): RedirectResponse
    {
        $data = $request->validate(['to' => 'required|in:active,suspended,terminated']);
        $this->service->transition($agent, $data['to']);

        return back()->with('success', "Status {$agent->code} → {$data['to']}.");
    }

    public function storeScheme(Agent $agent, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:40', 'name' => 'required|string|max:160',
            'basis' => 'required|in:flat,percent,slab,target_bonus',
            'flat_amount_idr' => 'nullable|integer|min:0', 'rate_percent' => 'nullable|numeric|min:0|max:100',
            'scope' => 'required|in:all,sku,channel', 'scope_ref' => 'nullable|string|max:60',
            'target_amount_idr' => 'nullable|integer|min:0', 'bonus_amount_idr' => 'nullable|integer|min:0',
            'level' => 'required|integer|min:0|max:5', 'override_rate_percent' => 'nullable|numeric|min:0|max:100',
            'valid_from' => 'required|date', 'valid_until' => 'nullable|date|after:valid_from',
        ]);
        $this->service->saveScheme($agent, $data);

        return back()->with('success', 'Skema komisi disimpan.');
    }

    public function recordAttribution(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'agent_id' => 'required|uuid|exists:agy_agents,id',
            'reference_id' => 'required|string|max:60', 'source' => 'required|in:referral,lead,agent_code',
            'rule' => 'required|in:last_touch,first_touch', 'touched_at' => 'required|date',
            'expires_at' => 'nullable|date|after:touched_at',
        ]);
        $this->service->recordAttribution($data);

        return back()->with('success', 'Atribusi penjualan tercatat.');
    }

    public function accrueSale(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'agent_id' => 'required|uuid|exists:agy_agents,id',
            'reference_id' => 'required|string|max:60', 'base_amount_idr' => 'required|integer|gt:0',
            'hold_days' => 'required|integer|min:0|max:365',
        ]);
        $accrual = $this->service->accrueSale(
            Agent::findOrFail($data['agent_id']),
            $data['reference_id'],
            (int) $data['base_amount_idr'],
            (int) $data['hold_days'],
            $request->user(),
        );

        return back()->with('success', 'Akrual komisi '.number_format($accrual->amount_idr)." (hold s/d {$accrual->hold_until->toDateString()}).");
    }

    public function clawback(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'accrual_id' => 'required|integer|exists:agy_commission_accruals,id',
            'amount_idr' => 'required|integer|gt:0', 'reason' => 'required|string|max:300',
        ]);
        $this->service->recordClawback((int) $data['accrual_id'], (int) $data['amount_idr'], $data['reason'], $request->user());

        return back()->with('success', 'Clawback komisi tercatat.');
    }

    public function releaseHold(): RedirectResponse
    {
        $count = $this->service->releaseHolds();

        return back()->with('success', "{$count} akrual lewat masa retur → payable.");
    }

    public function storePayout(Agent $agent, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period' => 'required|string|max:8', 'withholding_rate_percent' => 'nullable|numeric|min:0|max:100',
        ]);
        $payout = $this->service->createPayout(
            $agent, $data['period'], (float) ($data['withholding_rate_percent'] ?? 0), $request->user(),
        );

        return back()->with('success', "Payout {$payout->number} gross ".number_format($payout->gross_idr).', net '.number_format($payout->net_idr).'.');
    }

    public function approvePayout(Payout $payout, Request $request): RedirectResponse
    {
        try {
            $this->service->approvePayout($payout, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->errors()->add('payout', $e->getMessage());
        }

        return back()->with('success', "Payout {$payout->number} disetujui.");
    }
}
