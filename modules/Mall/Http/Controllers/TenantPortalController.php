<?php

declare(strict_types=1);

namespace Modules\Mall\Http\Controllers;

use App\Http\Controllers\Controller;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mall\Application\Actions\PayInvoiceAction;
use Modules\Mall\Application\Actions\RequestOvertimeAction;
use Modules\Mall\Application\Services\TenantSalesService;
use Modules\Mall\Domain\Enums\InvoiceStatus;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\TenantSalesReport;

class TenantPortalController extends Controller
{
    /**
     * Dapatkan tenant yang terkait dengan user saat ini, dengan pengamanan IDOR ketat.
     */
    protected function resolveTenant(Request $request): Tenant
    {
        $user = $request->user();

        if ($user->isAdmin() || $user->isMallAdmin()) {
            // Admin dapat memilih tenant via parameter atau mengambil tenant pertama
            $tenantId = $request->query('tenant_id') ?? $request->input('tenant_id');
            if ($tenantId) {
                return Tenant::findOrFail((int) $tenantId);
            }

            $first = Tenant::first();
            if ($first) {
                return $first;
            }

            abort(404, 'Belum ada data tenant di sistem.');
        }

        // Untuk user dengan role tenant, kunci hanya ke tenant miliknya
        $tenant = Tenant::where('user_id', $user->id)->first();
        if (! $tenant) {
            abort(403, 'Akun Anda belum ditautkan dengan profil tenant mall.');
        }

        return $tenant;
    }

    public function index(Request $request): View
    {
        $tenant = $this->resolveTenant($request);
        $user = $request->user();

        $walletAccount = $user->walletAccount('IDR');
        $walletBalance = BigDecimal::of($walletAccount->cached_balance ?: '0')->toInt();

        $activeLeases = Lease::with(['unit.zone', 'property'])
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [LeaseStatus::ACTIVE, LeaseStatus::SUSPENDED])
            ->get();

        $invoices = Invoice::with(['lease.unit'])
            ->where('tenant_id', $tenant->id)
            ->orderBy('id', 'desc')
            ->take(10)
            ->get();

        $currentMonth = date('Y-m');
        $salesReport = TenantSalesReport::where('tenant_id', $tenant->id)
            ->where('period_month', $currentMonth)
            ->first();

        $unpaidInvoices = $invoices->filter(fn ($i) => in_array($i->status, [InvoiceStatus::ISSUED, InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::OVERDUE]));
        $totalOutstanding = $unpaidInvoices->sum(fn ($i) => $i->remainingAmount());

        return view('mall::portal.index', [
            'tenant' => $tenant,
            'walletBalance' => $walletBalance,
            'activeLeases' => $activeLeases,
            'invoices' => $invoices,
            'currentMonth' => $currentMonth,
            'salesReport' => $salesReport,
            'totalOutstanding' => $totalOutstanding,
        ]);
    }

    public function showInvoice(Request $request, int $id): View
    {
        $tenant = $this->resolveTenant($request);
        $invoice = Invoice::with(['lines', 'lease.unit', 'property'])->findOrFail($id);

        // IDOR Protection: Tenant tidak boleh mengakses invoice milik tenant lain
        if ($invoice->tenant_id !== $tenant->id && ! $request->user()->isAdmin() && ! $request->user()->isMallAdmin()) {
            abort(403, 'Akses ditolak. Tagihan ini bukan milik tenant Anda.');
        }

        $walletAccount = $request->user()->walletAccount('IDR');
        $walletBalance = BigDecimal::of($walletAccount->cached_balance ?: '0')->toInt();

        return view('mall::portal.invoice', [
            'tenant' => $tenant,
            'invoice' => $invoice,
            'walletBalance' => $walletBalance,
        ]);
    }

    public function payInvoice(Request $request, int $id, PayInvoiceAction $action): RedirectResponse
    {
        $tenant = $this->resolveTenant($request);
        $invoice = Invoice::findOrFail($id);

        // IDOR Protection
        if ($invoice->tenant_id !== $tenant->id && ! $request->user()->isAdmin() && ! $request->user()->isMallAdmin()) {
            abort(403, 'Akses ditolak. Anda tidak berhak membayar tagihan tenant lain.');
        }

        $validated = $request->validate([
            'amount' => 'required|integer|min:1000',
            'pin' => 'required|string',
        ]);

        try {
            $updated = $action->execute(
                invoice: $invoice,
                paymentAmount: (int) $validated['amount'],
                pin: (string) $validated['pin'],
                user: $request->user()
            );

            $msg = $updated->isPaid()
                ? "Pembayaran tagihan #{$updated->invoice_number} sebesar Rp ".number_format($validated['amount']).' berhasil. Tagihan telah LUNAS.'
                : "Pembayaran sebagian tagihan #{$updated->invoice_number} sebesar Rp ".number_format($validated['amount']).' berhasil diterima.';

            return redirect()->route('mall.portal.invoice', $invoice->id)->with('success', $msg);
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function sales(Request $request): View
    {
        $tenant = $this->resolveTenant($request);

        $leases = Lease::where('tenant_id', $tenant->id)
            ->whereIn('status', [LeaseStatus::ACTIVE, LeaseStatus::SUSPENDED])
            ->get();

        $reports = TenantSalesReport::with('lease.unit')
            ->where('tenant_id', $tenant->id)
            ->orderBy('period_month', 'desc')
            ->get();

        return view('mall::portal.sales', [
            'tenant' => $tenant,
            'leases' => $leases,
            'reports' => $reports,
        ]);
    }

    public function storeSales(Request $request, TenantSalesService $service): RedirectResponse
    {
        $tenant = $this->resolveTenant($request);

        $validated = $request->validate([
            'lease_id' => 'required|integer',
            'period_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'gross_sales' => 'required|integer|min:0',
            'net_sales' => 'required|integer|min:0',
            'transaction_count' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $lease = Lease::findOrFail((int) $validated['lease_id']);

        // IDOR Protection
        if ($lease->tenant_id !== $tenant->id && ! $request->user()->isAdmin() && ! $request->user()->isMallAdmin()) {
            abort(403, 'Akses ditolak. Kontrak sewa bukan milik tenant Anda.');
        }

        $service->recordManualSales(
            lease: $lease,
            periodMonth: $validated['period_month'],
            grossSales: (int) $validated['gross_sales'],
            netSales: (int) $validated['net_sales'],
            txCount: (int) ($validated['transaction_count'] ?? 0),
            notes: $validated['notes'] ?? null
        );

        return redirect()->route('mall.portal.sales')
            ->with('success', "Laporan omzet periode {$validated['period_month']} berhasil disimpan.");
    }

    public function requestOvertime(Request $request, RequestOvertimeAction $action): RedirectResponse
    {
        $tenant = $this->resolveTenant($request);

        $validated = $request->validate([
            'lease_id' => 'required|integer',
            'date' => 'required|date',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'hours' => 'required|numeric|min:0.5|max:12',
            'reason' => 'required|string|max:500',
        ]);

        $lease = Lease::findOrFail((int) $validated['lease_id']);

        // IDOR Protection
        if ($lease->tenant_id !== $tenant->id && ! $request->user()->isAdmin() && ! $request->user()->isMallAdmin()) {
            abort(403, 'Akses ditolak.');
        }

        $action->execute(
            lease: $lease,
            date: $validated['date'],
            startTime: $validated['start_time'],
            endTime: $validated['end_time'],
            hours: (float) $validated['hours'],
            reason: $validated['reason']
        );

        return redirect()->route('mall.portal.index')
            ->with('success', "Pengajuan lembur AC untuk tanggal {$validated['date']} berhasil dikirim.");
    }
}
