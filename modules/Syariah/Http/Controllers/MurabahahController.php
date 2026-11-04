<?php

declare(strict_types=1);

namespace Modules\Syariah\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Syariah\Application\Services\MurabahahService;
use Modules\Syariah\Domain\Models\MurabahahContract;
use Modules\Syariah\Http\Requests\CollectTazirRequest;
use Modules\Syariah\Http\Requests\PayMurabahahInstallmentRequest;
use Modules\Syariah\Http\Requests\ProposeMurabahahRequest;
use Modules\Syariah\Http\Requests\ReviewDpsRequest;
use Throwable;

class MurabahahController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly MurabahahService $service) {}

    public function index(): View
    {
        return view('syariah::murabahah', [
            'contracts' => $this->service->listContracts(),
            'summary' => $this->service->summary(),
        ]);
    }

    public function propose(ProposeMurabahahRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $contract = $this->service->propose(
                customerUserId: (int) $data['customer_user_id'],
                supplierPartyRef: (int) $data['supplier_party_ref'],
                assetDescription: $data['asset_description'],
                costPriceIdr: (int) $data['cost_price_idr'],
                marginAmountIdr: (int) $data['margin_amount_idr'],
                tenorMonths: (int) $data['tenor_months'],
                proposedByUserId: (int) $request->user()->id,
                idempotencyKey: $data['idempotency_key'],
            );

            return redirect()->route('syariah.murabahah.index')
                ->with('success', "Pengajuan akad murabahah {$contract->contract_code} berhasil dibuat.");
        } catch (Throwable $e) {
            return back()->withErrors(['murabahah' => $e->getMessage()])->withInput();
        }
    }

    public function approveOfficer(Request $request, MurabahahContract $contract): RedirectResponse
    {
        try {
            $this->service->approveByOfficer($contract, (int) $request->user()->id);

            return back()->with('success', "Akad {$contract->contract_code} disetujui petugas syariah; menunggu reviu DPS.");
        } catch (Throwable $e) {
            return back()->withErrors(['murabahah' => $e->getMessage()]);
        }
    }

    public function purchaseAsset(Request $request, MurabahahContract $contract): RedirectResponse
    {
        $key = (string) ($request->input('idempotency_key') ?: $request->header('Idempotency-Key', ''));
        if ($key === '') {
            return back()->withErrors(['idempotency_key' => 'Idempotency-Key wajib diisi.']);
        }

        try {
            $this->service->purchaseAsset($contract, $key);

            return back()->with('success', "Barang murabahah untuk akad {$contract->contract_code} telah dibeli oleh bank.");
        } catch (Throwable $e) {
            return back()->withErrors(['murabahah' => $e->getMessage()]);
        }
    }

    public function signContract(Request $request, MurabahahContract $contract): RedirectResponse
    {
        $key = (string) ($request->input('idempotency_key') ?: $request->header('Idempotency-Key', ''));
        if ($key === '') {
            return back()->withErrors(['idempotency_key' => 'Idempotency-Key wajib diisi.']);
        }

        try {
            $this->service->signContract($contract, $key);

            return back()->with('success', "Akad jual murabahah {$contract->contract_code} telah ditandatangani dan jadwal angsuran diterbitkan.");
        } catch (Throwable $e) {
            return back()->withErrors(['murabahah' => $e->getMessage()]);
        }
    }

    public function payInstallment(PayMurabahahInstallmentRequest $request, MurabahahContract $contract): RedirectResponse
    {
        $this->authorize('view', $contract);
        $data = $request->validated();

        try {
            $schedule = $this->service->payInstallment(
                contract: $contract,
                installmentNo: (int) $data['installment_no'],
                idempotencyKey: $data['idempotency_key'],
            );

            return back()->with('success', "Angsuran ke-{$schedule->installment_no} akad {$contract->contract_code} lunas.");
        } catch (Throwable $e) {
            return back()->withErrors(['murabahah' => $e->getMessage()]);
        }
    }

    public function collectTazir(CollectTazirRequest $request, MurabahahContract $contract): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->service->collectTazirPenalty(
                contract: $contract,
                amountIdr: (int) $data['amount_idr'],
                reason: $data['reason'],
                idempotencyKey: $data['idempotency_key'],
            );

            return back()->with('success', "Denda ta'zir akad {$contract->contract_code} disalurkan ke dana kebajikan Qardhul Hasan.");
        } catch (Throwable $e) {
            return back()->withErrors(['murabahah' => $e->getMessage()]);
        }
    }

    public function reviewIndex(): View
    {
        return view('syariah::review', [
            'contracts' => $this->service->listContracts(),
            'summary' => $this->service->summary(),
        ]);
    }

    public function reviewApprove(ReviewDpsRequest $request, MurabahahContract $contract): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->service->reviewByDps($contract, (int) $request->user()->id, $data['opinion_note']);

            return redirect()->route('syariah.review.index')
                ->with('success', "Opini kepatuhan DPS untuk akad {$contract->contract_code} telah dicatat.");
        } catch (Throwable $e) {
            return back()->withErrors(['review' => $e->getMessage()]);
        }
    }

    public function portalIndex(Request $request): View
    {
        $user = $request->user();
        $scopeUserId = $user->role === 'nasabah' ? (int) $user->id : null;

        return view('syariah::portal', [
            'contracts' => $this->service->listContracts($scopeUserId),
            'summary' => $this->service->summary($scopeUserId),
        ]);
    }

    public function portalShow(MurabahahContract $contract): View
    {
        $this->authorize('view', $contract);

        return view('syariah::portal_show', [
            'contract' => $contract->load(['schedules', 'tazirPenalties']),
        ]);
    }
}
