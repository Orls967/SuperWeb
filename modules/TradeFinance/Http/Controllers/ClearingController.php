<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\TradeFinance\Application\Services\Clearing\ClearingEscrowService;
use Modules\TradeFinance\Domain\Models\Clearing\ClearingEscrow;
use Modules\TradeFinance\Http\Requests\DepositEscrowRequest;
use Modules\TradeFinance\Http\Requests\ReleaseEscrowRequest;
use Throwable;

class ClearingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ClearingEscrowService $service) {}

    private function scopeUserId(Request $request): ?int
    {
        return in_array($request->user()->role, ['admin', 'tf_officer'], true) ? null : (int) $request->user()->id;
    }

    public function index(Request $request): View
    {
        $scope = $this->scopeUserId($request);

        return view('trade_finance::clearing', [
            'escrows' => $this->service->listFor($scope),
            'summary' => $this->service->summary($scope),
            'canOperate' => $scope === null,
        ]);
    }

    public function show(Request $request, ClearingEscrow $escrow): View
    {
        $this->authorize('view', $escrow);

        return view('trade_finance::clearing_show', [
            'escrow' => $escrow,
            'canOperate' => $this->scopeUserId($request) === null,
        ]);
    }

    public function deposit(DepositEscrowRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Importir hanya boleh mendanai escrow atas namanya sendiri (anti-IDOR).
        abort_if(
            $request->user()->role === 'importer' && (int) $data['importer_user_id'] !== (int) $request->user()->id,
            403
        );

        try {
            $escrow = $this->service->deposit(
                letterOfCreditId: $data['letter_of_credit_id'],
                importerUserId: (int) $data['importer_user_id'],
                exporterUserId: (int) $data['exporter_user_id'],
                usdtMicro: (int) $data['usdt_micro'],
                idempotencyKey: $data['idempotency_key'],
            );

            return redirect()->route('trade_finance.clearing.show', $escrow)
                ->with('success', "Escrow USDT {$escrow->escrow_code} berhasil didanai.");
        } catch (Throwable $e) {
            return back()->withErrors(['deposit' => $e->getMessage()])->withInput();
        }
    }

    public function release(ReleaseEscrowRequest $request, ClearingEscrow $escrow): RedirectResponse
    {
        $data = $request->validated();

        try {
            $released = $this->service->release(
                escrow: $escrow,
                documentType: $data['document_type'],
                documentHash: $data['document_hash'],
                idempotencyKey: $data['idempotency_key'],
                actorUserId: (int) $request->user()->id,
            );

            return redirect()->route('trade_finance.clearing.show', $released)
                ->with('success', "Escrow {$released->escrow_code} dirilis ke eksportir setelah verifikasi {$released->release_document_type}.");
        } catch (Throwable $e) {
            return back()->withErrors(['release' => $e->getMessage()]);
        }
    }
}
