<?php

declare(strict_types=1);

namespace Modules\Distribution\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Distribution\Application\Services\DistributionService;
use Modules\Distribution\Domain\Models\ArInvoice;
use Modules\Distribution\Domain\Models\Distributor;
use Modules\Distribution\Domain\Models\Scorecard;
use Modules\Distribution\Domain\Models\Territory;

class DistributionController extends Controller
{
    public function __construct(private readonly DistributionService $service) {}

    public function index(): View
    {
        return view('distribution::index', [
            'distributors' => Distributor::withCount(['children', 'outlets'])
                ->orderBy('code')->get(),
            'territories' => Territory::with('parent')->orderBy('level')->orderBy('name')->get(),
            'conflicts' => $this->service->territoryConflicts(),
            'scorecards' => Scorecard::with('distributor')->orderByDesc('period')->limit(30)->get(),
        ]);
    }

    public function storeDistributor(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:40', 'name' => 'required|string|max:180',
            'kind' => 'required|in:distributor,sub_distributor,agent,dealer',
            'parent_id' => 'nullable|uuid|exists:dist_distributors,id',
            'party_id' => 'nullable|uuid|exists:pty_parties,id',
            'outlet_code' => 'nullable|string|max:40',
            'payment_terms_days' => 'required|integer|min:0|max:365',
            'credit_limit_idr' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);
        $distributor = $this->service->registerDistributor($data);

        return back()->with('success', "Distributor {$distributor->code} terdaftar (onboarding).");
    }

    public function storeTerritory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:40', 'name' => 'required|string|max:120',
            'level' => 'required|in:province,city,district', 'parent_id' => 'nullable|integer|exists:dist_territories,id',
        ]);
        $this->service->createTerritory($data);

        return back()->with('success', 'Wilayah dibuat.');
    }

    public function storeCoverage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'distributor_id' => 'required|uuid|exists:dist_distributors,id',
            'territory_id' => 'required|integer|exists:dist_territories,id',
            'exclusive' => 'sometimes|boolean',
            'valid_from' => 'required|date', 'valid_until' => 'nullable|date|after:valid_from',
        ]);

        try {
            $this->service->coverTerritory(
                Distributor::findOrFail($data['distributor_id']),
                Territory::findOrFail($data['territory_id']),
                (bool) ($data['exclusive'] ?? false),
                $data['valid_from'],
                $data['valid_until'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->errors()->add('coverage', $e->getMessage());
        }

        return back()->with('success', 'Coverage wilayah disimpan.');
    }

    public function storeSecurity(Distributor $distributor, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => 'required|in:bank_guarantee,deposit',
            'amount_idr' => 'required|integer|gt:0',
            'reference' => 'nullable|string|max:80', 'expires_at' => 'nullable|date',
        ]);
        $this->service->recordSecurity($distributor, $data);

        return back()->with('success', 'Jaminan onboarding tercatat.');
    }

    public function submitOnboarding(Distributor $distributor, Request $request): RedirectResponse
    {
        try {
            $this->service->submitOnboarding($distributor, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->errors()->add('onboarding', $e->getMessage());
        }

        return back()->with('success', 'Onboarding diajukan (procurement → admin).');
    }

    public function approveOnboarding(Distributor $distributor, Request $request): RedirectResponse
    {
        $data = $request->validate(['credit_limit_idr' => 'required|integer|min:0']);
        try {
            $this->service->approveOnboarding($distributor, $request->user(), (int) $data['credit_limit_idr']);
        } catch (\InvalidArgumentException $e) {
            return back()->errors()->add('onboarding', $e->getMessage());
        }

        return back()->with('success', "Distributor {$distributor->code} disetujui.");
    }

    public function transition(Distributor $distributor, Request $request): RedirectResponse
    {
        $data = $request->validate(['to' => 'required|in:approved,suspended,blocked,terminated']);
        try {
            $this->service->transition($distributor, $data['to']);
        } catch (\InvalidArgumentException $e) {
            return back()->errors()->add('status', $e->getMessage());
        }

        return back()->with('success', "Status {$distributor->code} → {$data['to']}.");
    }

    public function storeInvoice(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'distributor_id' => 'required|uuid|exists:dist_distributors,id',
            'amount_idr' => 'required|integer|gt:0',
            'invoice_date' => 'required|date', 'due_date' => 'required|date|after_or_equal:invoice_date',
            'source_type' => 'required|in:sales,retur,denda,manual', 'source_ref' => 'nullable|string|max:80',
        ]);
        $distributor = Distributor::findOrFail($data['distributor_id']);
        try {
            $invoice = $this->service->issueInvoice(
                $distributor, (int) $data['amount_idr'], $data['invoice_date'], $data['due_date'],
                $request->user(), $data['source_type'], $data['source_ref'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->errors()->add('invoice', $e->getMessage());
        }

        return back()->with('success', "Tagihan {$invoice->number} terbit.");
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_id' => 'required|uuid|exists:dist_ar_invoices,id',
            'amount_idr' => 'required|integer|gt:0', 'paid_at' => 'required|date',
            'method' => 'required|in:transfer,cash,giro,ewallet', 'reference' => 'nullable|string|max:80',
        ]);

        try {
            $this->service->payInvoice(
                ArInvoice::findOrFail($data['invoice_id']),
                (int) $data['amount_idr'], $data['paid_at'], $request->user(),
                $data['method'], $data['reference'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->errors()->add('payment', $e->getMessage());
        }

        return back()->with('success', 'Pembayaran dicatat.');
    }

    public function storeTarget(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'distributor_id' => 'required|uuid|exists:dist_distributors,id',
            'product_sku' => 'required|string|max:60', 'period' => 'required|string|max:8',
            'target_qty' => 'required|numeric|gt:0', 'basis' => 'required|in:sell_in,sell_out',
        ]);
        $distributor = Distributor::findOrFail($data['distributor_id']);
        unset($data['distributor_id']);
        $this->service->setTarget($distributor, $data);

        return back()->with('success', 'Target penjualan disimpan.');
    }

    public function evaluateTier(Distributor $distributor): RedirectResponse
    {
        $result = $this->service->evaluateTier($distributor);

        return back()->with('success', $result['changed']
            ? "Tier {$distributor->code} → {$result['tier']}"
            : "Tier {$distributor->code} tetap {$result['tier']}.");
    }

    public function storeOutlet(Distributor $distributor, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:40', 'name' => 'required|string|max:180',
            'segment' => 'required|in:retail,horeca,modern,wholesale',
            'city' => 'nullable|string|max:60', 'address' => 'nullable|string|max:255',
            'territory_id' => 'nullable|integer|exists:dist_territories,id',
        ]);

        try {
            $this->service->addOutlet($distributor, $data);
        } catch (\InvalidArgumentException $e) {
            return back()->errors()->add('outlet', $e->getMessage());
        }

        return back()->with('success', 'Outlet distributor ditambahkan.');
    }

    public function storeScorecard(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'distributor_id' => 'required|uuid|exists:dist_distributors,id',
            'period' => 'required|string|max:8',
            'fill_rate_percent' => 'nullable|numeric|between:0,100',
            'price_compliance_percent' => 'nullable|numeric|between:0,100',
        ]);
        $distributor = Distributor::findOrFail($data['distributor_id']);
        $this->service->computeScorecard($distributor, $data['period'], array_filter([
            'fill_rate_percent' => $data['fill_rate_percent'] ?? null,
            'price_compliance_percent' => $data['price_compliance_percent'] ?? null,
        ], fn ($v) => $v !== null));

        return back()->with('success', 'Scorecard dihitung.');
    }

    public function sweep(Request $request): RedirectResponse
    {
        $count = $this->service->sweepOverdue();

        return back()->with('success', "{$count} tagihan dikenai denda keterlambatan.");
    }

    public function show(Distributor $distributor): View
    {
        $distributor->load(['children', 'outlets', 'coverages.territory', 'securities', 'scorecards']);

        return view('distribution::show', [
            'distributor' => $distributor,
            'aging' => $this->service->agingReport($distributor),
            'discount' => $this->service->tierDiscountPercent($distributor->tier),
            'invoices' => $distributor->arInvoices()->orderByDesc('invoice_date')->limit(50)->get(),
            'outletSegments' => ['retail', 'horeca', 'modern', 'wholesale'],
        ]);
    }
}
