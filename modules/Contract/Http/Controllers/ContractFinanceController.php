<?php

declare(strict_types=1);

namespace Modules\Contract\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Contract\Application\Services\ContractAmendmentService;
use Modules\Contract\Application\Services\ContractFinanceService;
use Modules\Contract\Application\Services\ContractReportService;
use Modules\Contract\Application\Services\ContractRiskService;
use Modules\Contract\Application\Services\ContractUsageService;
use Modules\Contract\Application\Services\ContractUsageSyncService;
use Modules\Contract\Domain\Enums\ContractStatus;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\EscalationIndex;
use Modules\Contract\Domain\Models\PaymentSchedule;

/**
 * Fitur keuangan/kompliance kontrak (Fase 29.1–29.8).
 *
 * Semua jalur uang lewat `ContractFinanceService` (integer IDR, key deterministik).
 * Pajak, bea, dan arbitrase bersifat simulasi.
 */
class ContractFinanceController extends Controller
{
    public function __construct(
        private readonly ContractFinanceService $finance,
        private readonly ContractAmendmentService $amendment,
        private readonly ContractUsageService $usage,
        private readonly ContractUsageSyncService $usageSync,
        private readonly ContractReportService $reports,
        private readonly ContractRiskService $risk,
    ) {}

    // ── 29.1 Jadwal pembayaran ───────────────────────────────────────────

    /** Bangun jadwal termin (idempoten). */
    public function buildSchedule(Request $request, Contract $contract): RedirectResponse
    {
        $data = $request->validate([
            'count' => 'nullable|integer|min:1|max:60',
            'interval_months' => 'nullable|integer|min:1|max:60',
            'by_milestone' => 'nullable|boolean',
        ]);

        $schedules = $this->finance->buildSchedule($contract, [
            'count' => (int) ($data['count'] ?? 3),
            'interval_months' => (int) ($data['interval_months'] ?? 3),
            'by_milestone' => (bool) ($data['by_milestone'] ?? false),
        ]);

        return back()->with('success', count($schedules).' termin berhasil dibentuk dari jadwal pembayaran kontrak.');
    }

    /** Bayar satu termin (dompet pembayar + posting ledger idempoten). */
    public function paySchedule(Request $request, Contract $contract, PaymentSchedule $schedule): RedirectResponse
    {
        abort_unless($schedule->contract_id === $contract->id, 404);

        $data = $request->validate([
            'amount_idr' => 'required|integer|min:1',
            'idempotency_key' => ['required', 'string', 'max:64'],
            'payer_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $payerUserId = $data['payer_user_id'] ?? auth()->id();

        $this->finance->paySchedule(
            schedule: $schedule,
            amountIdr: (int) $data['amount_idr'],
            idempotencyKey: $data['idempotency_key'],
            payerUserId: (int) $payerUserId,
        );

        return back()->with('success', 'Pembayaran termin tercatat di ledger.');
    }

    /** Bayar uang muka (advance) kontrak. */
    public function payAdvance(Request $request, Contract $contract): RedirectResponse
    {
        $data = $request->validate([
            'amount_idr' => 'required|integer|min:1',
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        $this->finance->payAdvance(
            contract: $contract,
            payerUserId: (int) auth()->id(),
            amountIdr: (int) $data['amount_idr'],
            idempotencyKey: $data['idempotency_key'],
        );

        return back()->with('success', 'Uang muka kontrak tercatat di ledger.');
    }

    // ── 29.2 Denda & waiver ──────────────────────────────────────────────

    /** Hitung denda keterlambatan satu termin (tanpa posting). */
    public function showPenalty(PaymentSchedule $schedule): RedirectResponse
    {
        $calc = $this->finance->computeLatePenalty($schedule);

        return back()->with('status', 'penalty-calculated')
            ->with('penalty_days', $calc['days_late'])
            ->with('penalty_amount', $calc['penalty_idr']);
    }

    /** Bayar denda keterlambatan. */
    public function payPenalty(Request $request, PaymentSchedule $schedule): RedirectResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        $paid = $this->finance->payLatePenalty(
            schedule: $schedule,
            payerUserId: (int) auth()->id(),
            idempotencyKey: $data['idempotency_key'],
        );

        return back()->with(
            'success',
            $paid > 0
                ? 'Denda keterlambatan '.number_format($paid).' IDR tercatat di ledger.'
                : 'Tidak ada denda terutang (belum terlambat / dibebaskan).'
        );
    }

    /** Ajukan pembebasan denda via approval engine (four-eyes). */
    public function requestWaiver(Request $request, PaymentSchedule $schedule): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $this->finance->requestPenaltyWaiver($schedule, $user, $data['reason']);

        return back()->with('success', 'Permohonan pembebasan denda diajukan (butuh persetujuan legal & admin).');
    }

    /** Terapkan pembebasan (dipanggil setelah approval selesai — role legal/admin). */
    public function applyWaiver(PaymentSchedule $schedule): RedirectResponse
    {
        $this->finance->applyPenaltyWaiver($schedule);

        return back()->with('success', 'Denda dibebaskan (waiver direkam).');
    }

    // ── 29.3 Eskalasi harga ──────────────────────────────────────────────

    /** Pratinjau faktor eskalasi saat ini. */
    public function previewEscalation(Contract $contract): RedirectResponse
    {
        $esc = $this->finance->computeEscalation($contract);

        return back()->with('status', 'escalation-preview')
            ->with('esc_factor', round($esc['factor'], 6))
            ->with('esc_index', $esc['current_index'])
            ->with('esc_applied', $esc['applied']);
    }

    /** Terapkan eskalasi: perbarui nilai kontrak & termin yang belum dibayar. */
    public function applyEscalation(Contract $contract): RedirectResponse
    {
        $result = $this->finance->applyEscalation($contract);

        if (! $result['applied']) {
            return back()->with('error', 'Eskalasi tidak aktif atau faktor bernilai 1 (tidak ada perubahan).');
        }

        return back()->with('success', 'Eskalasi diterapkan: faktor '.round($result['factor'], 4).', nilai kontrak menjadi '.number_format($result['new_total_idr']).' IDR.');
    }

    // ── 29.4 Amandemen & addendum ────────────────────────────────────────

    public function amend(Request $request, Contract $contract): RedirectResponse
    {
        $data = $request->validate([
            'total_value_idr' => 'nullable|integer|min:0',
            'end_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'kind' => 'nullable|in:amendment,addendum',
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $amendment = $this->amendment->amend($contract, $data, auth()->user()?->name);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('contract.show', $contract)
            ->with('success', 'Amandemen direkam: versi hash-chain baru + jadwal termin dihitung ulang.');
    }

    // ── 29.5 Rekonsiliasi pemakaian ──────────────────────────────────────

    /** Sinkronkan transaksi riil → ctr_usage_ledger (idempoten). */
    public function syncUsage(Contract $contract): RedirectResponse
    {
        $recorded = $this->usageSync->syncAll();
        $util = $this->usage->utilization($contract);

        return back()->with(
            'success',
            'Sinkronisasi selesai ('.json_encode($recorded).'). Utilisasi kontrak ini: '.$util['percent'].'%.'
        );
    }

    // ── 29.7 Skor risiko ─────────────────────────────────────────────────

    public function rescoreRisk(Contract $contract): RedirectResponse
    {
        $result = $this->risk->score($contract);

        return back()->with(
            'success',
            'Skor risiko dihitung ulang: '.$result['score'].'/100 ('.count($result['flags']).' flag).'
        );
    }

    // ── 29.8 Laporan ─────────────────────────────────────────────────────

    public function reports(): View
    {
        return view('contract::reports', [
            'exposure' => $this->reports->exposure(),
            'aging' => $this->reports->obligationAging(),
            'expiring' => $this->reports->expiringSoon(90),
            'over80' => $this->usage->contractsOverThreshold(80),
            'indexes' => EscalationIndex::query()->orderBy('code')->orderByDesc('observed_at')->limit(20)->get()->groupBy('code'),
            'audit' => $this->reports->audit(),
            'activeContracts' => Contract::whereIn('status', [
                ContractStatus::Active->value,
                ContractStatus::Signed->value,
                ContractStatus::Suspended->value,
            ])->count(),
        ]);
    }
}
