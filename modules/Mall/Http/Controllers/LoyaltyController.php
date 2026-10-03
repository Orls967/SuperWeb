<?php

declare(strict_types=1);

namespace Modules\Mall\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Mall\Application\Actions\ClaimReceiptPointsAction;
use Modules\Mall\Application\Actions\RedeemVoucherAction;
use Modules\Mall\Application\Actions\SettleVouchersAction;
use Modules\Mall\Application\Actions\UseVoucherAction;
use Modules\Mall\Application\Queries\LoyaltyOverviewQuery;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\VoucherTemplate;

class LoyaltyController extends Controller
{
    public function index(LoyaltyOverviewQuery $query): View
    {
        $overview = $query->get();
        $user = auth()->user();
        $userPoints = $user ? $user->pointsBalance() : 0;
        $tenants = Tenant::where('is_active', true)->get();

        return view('mall::loyalty.index', [
            'overview' => $overview,
            'userPoints' => $userPoints,
            'tenants' => $tenants,
            'redeemIdempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function claimReceipt(Request $request, ClaimReceiptPointsAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'receipt_number' => ['required', 'string', 'max:50'],
            'receipt_amount' => ['required', 'integer', 'min:1000'],
            'receipt_date' => ['required', 'date'],
            'tenant_id' => ['nullable', 'exists:mall_tenants,id'],
        ]);

        try {
            $claim = $action->execute(
                user: $request->user(),
                receiptNumber: $validated['receipt_number'],
                receiptAmount: (int) $validated['receipt_amount'],
                receiptDate: $validated['receipt_date'],
                tenantId: ! empty($validated['tenant_id']) ? (int) $validated['tenant_id'] : null,
                processor: $request->user()
            );

            return back()->with('success', "Klaim berhasil disetujui! Anda memperoleh {$claim->points_earned} Duta Points (PTS).");
        } catch (\Throwable $e) {
            return back()->withErrors(['receipt_error' => $e->getMessage()]);
        }
    }

    public function redeem(
        Request $request,
        VoucherTemplate $template,
        RedeemVoucherAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $voucher = $action->execute(
                $request->user(),
                $template,
                $validated['idempotency_key'] ?? null
            );

            return back()->with('success', "Voucher {$template->title} berhasil ditukarkan! Kode voucher Anda: {$voucher->voucher_code}.");
        } catch (\Throwable $e) {
            return back()->withErrors(['redeem_error' => $e->getMessage()]);
        }
    }

    public function useVoucher(Request $request, UseVoucherAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'voucher_code' => ['required', 'string'],
            'tenant_id' => ['required', 'exists:mall_tenants,id'],
            'transaction_amount' => ['required', 'integer', 'min:1'],
            'transaction_ref' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $tenant = Tenant::findOrFail($validated['tenant_id']);
            $voucher = $action->execute(
                voucherCode: $validated['voucher_code'],
                tenant: $tenant,
                transactionAmount: (int) $validated['transaction_amount'],
                transactionRef: $validated['transaction_ref'] ?? null
            );

            return back()->with('success', "Voucher {$voucher->voucher_code} (Rp ".number_format($voucher->nominal_value).') sukses digunakan di tenant '.$tenant->brand_name.'.');
        } catch (\Throwable $e) {
            return back()->withErrors(['voucher_error' => $e->getMessage()]);
        }
    }

    public function settle(Request $request, SettleVouchersAction $action): RedirectResponse
    {
        try {
            $result = $action->execute();

            return back()->with('success', "Settlement mingguan berhasil: {$result['settled_count']} voucher telah dicairkan ke {$result['tenants_count']} tenant dengan total Rp ".number_format($result['total_amount']).'.');
        } catch (\Throwable $e) {
            return back()->withErrors(['settle_error' => $e->getMessage()]);
        }
    }
}
