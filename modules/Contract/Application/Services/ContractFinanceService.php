<?php

declare(strict_types=1);

namespace Modules\Contract\Application\Services;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Contract\Domain\Enums\PaymentScheduleKind;
use Modules\Contract\Domain\Enums\PaymentScheduleStatus;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\EscalationIndex;
use Modules\Contract\Domain\Models\PaymentSchedule;
use Modules\Contract\Domain\Models\PenaltyRule;
use Modules\Core\Contracts\ApprovalEngineInterface;

/**
 * Keuangan kontrak (Fase 29.1–29.3).
 *
 * Invarian uang:
 * - Seluruh nilai integer IDR; pembagian memakai BigDecimal + RoundingMode::HalfUp.
 * - Setiap posting ledger punya idempotency key deterministik dari sumber
 *   (kontrak + termin), sehingga retry tidak menggandakan pencatatan.
 * - Retensi (retention %) ditahan dari setiap termin dan menumpuk sebagai
 *   liabilitas sampai dibayarkan saat kontrak berakhir.
 */
class ContractFinanceService
{
    public function __construct(
        private readonly Ledger $ledger,
    ) {}

    // ── Akun ledger ──────────────────────────────────────────────────────

    /** Uang muka diterima (liabilitas, kredit saat advance masuk). */
    public const ACCT_ADVANCE = 'ctr:advance';

    /** Retensi piutang (menahan pembayaran ke rekanan). */
    public const ACCT_RETENTION_RECEIVABLE = 'ctr:retention_receivable';

    /** Retensi terutang (menahan pembayaran dari pelanggan). */
    public const ACCT_RETENTION_PAYABLE = 'ctr:retention_payable';

    public function ensureAccounts(): void
    {
        $defs = [
            [self::ACCT_ADVANCE, 'Uang Muka Kontrak', AccountKind::LIABILITY],
            [self::ACCT_RETENTION_RECEIVABLE, 'Retensi Kontrak (Receivable)', AccountKind::LIABILITY],
            [self::ACCT_RETENTION_PAYABLE, 'Retensi Kontrak (Payable)', AccountKind::LIABILITY],
        ];

        foreach ($defs as [$code, $name, $kind]) {
            LedgerAccount::firstOrCreate(
                ['code' => $code, 'asset_code' => 'IDR'],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $name,
                    'kind' => $kind->value,
                    'allow_negative' => true,
                    'cached_balance' => '0',
                    'is_frozen' => false,
                ]
            );
        }
    }

    // ── 29.1 Jadwal pembayaran ───────────────────────────────────────────

    /**
     * Bangun jadwal pembayaran dari nilai kontrak: advance → termin berkala/milestone.
     *
     * Idempoten: memanggil ulang pada kontrak yang sama mengembalikan
     * jadwal yang sudah ada, bukan menduplikasi termin.
     *
     * @param  array{count?: int, interval_months?: int, by_milestone?: bool}  $options
     * @return array<int, PaymentSchedule>
     */
    public function buildSchedule(Contract $contract, array $options = [], bool $replaceUnpaid = false): array
    {
        return DB::transaction(function () use ($contract, $options, $replaceUnpaid) {
            /** @var Contract $lockedContract */
            $lockedContract = Contract::query()->lockForUpdate()->findOrFail($contract->getKey());
            $existing = $lockedContract->paymentSchedules()->lockForUpdate()->get();
            if ($existing->isNotEmpty() && ! $replaceUnpaid) {
                return $existing->all();
            }
            if ($replaceUnpaid && $existing->contains(fn (PaymentSchedule $schedule) => $schedule->status !== PaymentScheduleStatus::Pending)) {
                throw new InvalidArgumentException('Jadwal dibayar/sebagian/waived tidak dapat diganti saat regenerate.');
            }
            if ($replaceUnpaid && $existing->isNotEmpty()) {
                foreach ($existing as $schedule) {
                    $schedule->delete();
                }
            }
            $contract = $lockedContract;

            $schedules = [];
            $totalValue = (int) $contract->total_value_idr;

            if ($totalValue <= 0) {
                return [];
            }

            // Uang muka (jika diisi) menjadi termin pertama.
            $advance = (int) $contract->advance_amount_idr;
            if ($advance > 0) {
                $schedules[] = $this->makeSchedule(
                    $contract,
                    PaymentScheduleKind::Advance,
                    $contract->start_date ?? now(),
                    $advance,
                    'Uang muka awal kontrak'
                );
            }

            $remaining = max(0, $totalValue - $advance);
            $byMilestone = (bool) ($options['by_milestone'] ?? false);

            if ($byMilestone) {
                // Satu termin per milestone, proporsional terhadap amount milestone.
                $milestones = $contract->milestones()->where('amount_idr', '>', 0)->get();
                $totalMilestoneValue = (int) $milestones->sum('amount_idr');

                if ($totalMilestoneValue > 0 && $remaining > 0) {
                    foreach ($milestones as $milestone) {
                        $portion = (int) BigDecimal::of($remaining)
                            ->multipliedBy($milestone->amount_idr)
                            ->dividedBy($totalMilestoneValue, 0, RoundingMode::HalfUp)
                            ->__toString();

                        $schedules[] = $this->makeSchedule(
                            $contract,
                            PaymentScheduleKind::Milestone,
                            $milestone->due_date,
                            $portion,
                            "Termin milestone: {$milestone->title}",
                            $milestone->id
                        );
                    }
                } else {
                    $schedules[] = $this->makeSchedule(
                        $contract,
                        PaymentScheduleKind::Milestone,
                        $contract->start_date ?? now(),
                        $remaining,
                        'Pelunasan berdasarkan milestone'
                    );
                }
            } else {
                $count = max(1, (int) ($options['count'] ?? 3));
                $intervalMonths = max(1, (int) ($options['interval_months'] ?? 3));
                $base = (int) ($contract->start_date?->getTimestamp() ?? now()->getTimestamp());

                $each = BigDecimal::of($remaining)->dividedBy($count, 0, RoundingMode::HalfUp);
                $allocated = BigDecimal::zero();

                for ($i = 0; $i < $count; $i++) {
                    $portion = $i === $count - 1
                        ? BigDecimal::of($remaining)->minus($allocated)
                        : $each;

                    $allocated = $allocated->plus($portion);

                    $dueDate = $contract->start_date
                        ? $contract->start_date->copy()->addMonths($intervalMonths * ($i + 1))
                        : now()->addMonths($intervalMonths * ($i + 1));

                    $schedules[] = $this->makeSchedule(
                        $contract,
                        PaymentScheduleKind::Term,
                        $dueDate,
                        (int) $portion->__toString(),
                        'Termin berkala ke-'.($i + 1)
                    );
                }
            }

            return $schedules;
        });
    }

    private function makeSchedule(
        Contract $contract,
        PaymentScheduleKind $kind,
        $dueDate,
        int $amountIdr,
        ?string $notes = null,
        ?string $milestoneId = null
    ): PaymentSchedule {
        // Retensi dipotong dari setiap termin (kecuali advance & retention sendiri).
        $retention = 0;
        if (in_array($kind, [PaymentScheduleKind::Term, PaymentScheduleKind::Milestone, PaymentScheduleKind::Periodic], true)
            && $contract->retention_percent > 0) {
            $retention = (int) BigDecimal::of($amountIdr)
                ->multipliedBy($contract->retention_percent)
                ->dividedBy(100, 0, RoundingMode::HalfUp)
                ->__toString();
        }

        return PaymentSchedule::create([
            'contract_id' => $contract->id,
            'milestone_id' => $milestoneId,
            'kind' => $kind,
            'due_date' => $dueDate,
            'amount_idr' => $amountIdr,
            'retention_amount_idr' => $retention,
            'paid_amount_idr' => 0,
            'status' => PaymentScheduleStatus::Pending,
            'notes' => $notes,
        ]);
    }

    /**
     * Bayar satu termin (amount <= balance termin).
     *
     * Posting: debit rekening penerima (wallet pemilik akun kontrak), kredit revenue
     * kontrak; retensi termin terkunci ke akun retensi selama dibayar.
     *
     * Idempotency key: `ctr:pay:{scheduleId}:{paidAmount}:{attemptSequence}` —
     * dipanggil oleh caller dengan `paidAmount` yang sudah deterministik.
     */
    public function paySchedule(
        PaymentSchedule $schedule,
        int $amountIdr,
        string $idempotencyKey,
        ?int $payerUserId = null
    ): PaymentSchedule {
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Nominal pembayaran harus lebih besar dari 0.');
        }

        return DB::transaction(function () use ($schedule, $amountIdr, $idempotencyKey, $payerUserId) {
            /** @var PaymentSchedule $locked */
            $locked = PaymentSchedule::query()->lockForUpdate()->findOrFail($schedule->getKey());

            if ($locked->status === PaymentScheduleStatus::Paid) {
                // Replay idempoten: termin sudah lunas, kembalikan apa adanya.
                return $locked;
            }

            if ($locked->status === PaymentScheduleStatus::Waived) {
                throw new InvalidArgumentException('Termin sudah dibebaskan dan tidak dapat dibayar.');
            }

            $balance = $locked->balanceDue();
            if ($amountIdr > $balance) {
                throw new InvalidArgumentException(
                    "Nominal pembayaran melebihi sisa termin (sisa: {$balance})."
                );
            }

            $this->ensureAccounts();

            // Retensi yang menumpuk pada termin ini ikut terkunci saat termin dibayar.
            $retentionOnSchedule = (int) $locked->retention_amount_idr;
            $netDue = max(0, $balance - $retentionOnSchedule);
            $netToPay = min($amountIdr, $netDue);

            $revenueCode = 'ctr:revenue:'.$locked->contract_id;

            $accounts = LedgerAccount::firstOrCreate(
                ['code' => $revenueCode, 'asset_code' => 'IDR'],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => "Pendapatan Kontrak {$locked->contract_id}",
                    'kind' => AccountKind::REVENUE->value,
                    'allow_negative' => false,
                    'cached_balance' => '0',
                    'is_frozen' => false,
                ]
            );

            $payerWallet = $payerUserId !== null
                ? $this->walletAccountFor($payerUserId)
                : null;

            if ($payerWallet === null && $netToPay > 0) {
                throw new InvalidArgumentException('Pembayar tidak memiliki dompet untuk menerima pembayaran.');
            }

            $entries = [];

            if ($netToPay > 0 && $payerWallet !== null) {
                $entries[] = PostingEntryDTO::forAccount($payerWallet->id, 'IDR', BigDecimal::of($netToPay)->negated());
                $entries[] = PostingEntryDTO::forAccount($accounts->id, 'IDR', BigDecimal::of($netToPay));
            }

            $retentionPart = min($retentionOnSchedule, $amountIdr - $netToPay);
            if ($retentionPart > 0) {
                // Menahan retensi: debit piutang retensi, kredit pendapatan.
                $retentionAcc = LedgerAccount::where('code', self::ACCT_RETENTION_RECEIVABLE)
                    ->where('asset_code', 'IDR')
                    ->firstOrFail();

                $entries[] = PostingEntryDTO::forAccount($retentionAcc->id, 'IDR', BigDecimal::of($retentionPart)->negated());
                $entries[] = PostingEntryDTO::forAccount($accounts->id, 'IDR', BigDecimal::of($retentionPart));
            }

            if ($entries !== []) {
                $this->ledger->post(new PostingDTO(
                    type: TransactionType::CONTRACT_PAYMENT->value,
                    description: "Pembayaran termin kontrak — {$locked->contract_id} ({$locked->kind->label()})",
                    idempotencyKey: $idempotencyKey,
                    entries: $entries,
                    referenceType: PaymentSchedule::class,
                    referenceId: $locked->getKey(),
                    meta: [
                        'schedule_id' => $locked->id,
                        'contract_id' => $locked->contract_id,
                        'amount' => $amountIdr,
                        'retention' => $retentionPart,
                        'net' => $netToPay,
                    ],
                    createdBy: $payerUserId,
                    postedAt: now(),
                ));
            }

            $locked->markPaid($amountIdr);

            return $locked->fresh();
        });
    }

    /**
     * Bayar uang muka (advance) kontrak: debit dompet, kredit akun liabilitas uang muka.
     */
    public function payAdvance(
        Contract $contract,
        int $payerUserId,
        int $amountIdr,
        string $idempotencyKey
    ): int {
        if ($amountIdr <= 0 || $amountIdr > (int) $contract->advance_amount_idr) {
            throw new InvalidArgumentException('Nominal uang muka tidak valid.');
        }

        return DB::transaction(function () use ($contract, $payerUserId, $amountIdr, $idempotencyKey) {
            /** @var Contract $locked */
            $locked = Contract::query()->lockForUpdate()->findOrFail($contract->getKey());

            $alreadyPaid = (int) $locked->advance_paid_idr;
            $remaining = (int) $locked->advance_amount_idr - $alreadyPaid;

            if ($amountIdr > $remaining) {
                throw new InvalidArgumentException("Sisa uang muka hanya {$remaining}.");
            }

            if ($remaining === 0) {
                return 0;
            }

            $this->ensureAccounts();

            $payerWallet = $this->walletAccountFor($payerUserId);
            $advanceAcc = LedgerAccount::where('code', self::ACCT_ADVANCE)
                ->where('asset_code', 'IDR')
                ->firstOrFail();

            $this->ledger->post(new PostingDTO(
                type: TransactionType::CONTRACT_ADVANCE->value,
                description: "Uang muka kontrak {$locked->contract_number}",
                idempotencyKey: $idempotencyKey,
                entries: [
                    PostingEntryDTO::forAccount($payerWallet->id, 'IDR', BigDecimal::of($amountIdr)->negated()),
                    PostingEntryDTO::forAccount($advanceAcc->id, 'IDR', BigDecimal::of($amountIdr)),
                ],
                referenceType: Contract::class,
                referenceId: $locked->getKey(),
                meta: ['contract_id' => $locked->id, 'amount' => $amountIdr],
                createdBy: $payerUserId,
                postedAt: now(),
            ));

            $locked->advance_paid_idr = $alreadyPaid + $amountIdr;
            $locked->save();

            return $amountIdr;
        });
    }

    // ── 29.2 Denda keterlambatan ────────────────────────────────────────

    /**
     * Hitung denda keterlambatan untuk satu termin (aturan aktif milik kontrak
     * atau aturan global).
     *
     * @return array{days_late: int, penalty_idr: int, rule: PenaltyRule|null}
     */
    public function computeLatePenalty(PaymentSchedule $schedule): array
    {
        if ($schedule->status === PaymentScheduleStatus::Paid
            || $schedule->status === PaymentScheduleStatus::Waived) {
            return ['days_late' => 0, 'penalty_idr' => 0, 'rule' => null];
        }

        $dueDate = $schedule->due_date;
        if ($dueDate === null || $dueDate->isFuture()) {
            return ['days_late' => 0, 'penalty_idr' => 0, 'rule' => null];
        }

        $daysLate = (int) now()->startOfDay()->diffInDays($dueDate->startOfDay(), false);
        $daysLate = abs($daysLate);

        $rule = PenaltyRule::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->where('contract_id', $schedule->contract_id)
                ->orWhereNull('contract_id'))
            ->orderByRaw('contract_id IS NULL') // kontrak khusus didahulukan
            ->first();

        if ($rule === null) {
            return ['days_late' => $daysLate, 'penalty_idr' => 0, 'rule' => null];
        }

        $penalty = $rule->computePenalty($daysLate, $schedule->balanceDue());

        return ['days_late' => $daysLate, 'penalty_idr' => $penalty, 'rule' => $rule];
    }

    /**
     * Bayar denda dari dompet pelaku (debit dompet, kredit revenue denda).
     *
     * Idempotency key: deterministik dari termin + hari terlambat.
     */
    public function payLatePenalty(
        PaymentSchedule $schedule,
        int $payerUserId,
        string $idempotencyKey
    ): int {
        $calc = $this->computeLatePenalty($schedule);
        $penalty = $calc['penalty_idr'];

        if ($penalty <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($schedule, $payerUserId, $penalty, $idempotencyKey) {
            $this->ensureAccounts();

            $payerWallet = $this->walletAccountFor($payerUserId);
            $revenueAcc = LedgerAccount::firstOrCreate(
                ['code' => 'ctr:penalty_revenue:IDR', 'asset_code' => 'IDR'],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Pendapatan Denda Kontrak',
                    'kind' => AccountKind::REVENUE->value,
                    'allow_negative' => false,
                    'cached_balance' => '0',
                    'is_frozen' => false,
                ]
            );

            $this->ledger->post(new PostingDTO(
                type: TransactionType::CONTRACT_PENALTY->value,
                description: "Denda keterlambatan termin {$schedule->id} ({$calc['days_late']} hari)",
                idempotencyKey: $idempotencyKey,
                entries: [
                    PostingEntryDTO::forAccount($payerWallet->id, 'IDR', BigDecimal::of($penalty)->negated()),
                    PostingEntryDTO::forAccount($revenueAcc->id, 'IDR', BigDecimal::of($penalty)),
                ],
                referenceType: PaymentSchedule::class,
                referenceId: $schedule->getKey(),
                meta: [
                    'schedule_id' => $schedule->id,
                    'contract_id' => $schedule->contract_id,
                    'days_late' => $calc['days_late'],
                    'penalty' => $penalty,
                ],
                createdBy: $payerUserId,
                postedAt: now(),
            ));

            return $penalty;
        });
    }

    /**
     * Ajukan pembebasan (waiver) denda via approval engine (29.2).
     *
     * @return object Approval request (dikembalikan ApprovalEngineInterface::submit)
     */
    public function requestPenaltyWaiver(
        PaymentSchedule $schedule,
        User $creator,
        string $reason
    ): object {
        return app(ApprovalEngineInterface::class)->submit(
            approvalType: 'CONTRACT_PENALTY_WAIVER',
            title: "Pembebasan Denda Termin {$schedule->id}",
            creator: $creator,
            approvable: $schedule,
            amount: (float) $this->computeLatePenalty($schedule)['penalty_idr'],
            steps: [['role' => 'legal'], ['role' => 'admin']],
            slaHours: 72,
            metadata: [
                'schedule_id' => $schedule->id,
                'contract_id' => $schedule->contract_id,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Terapkan pembebasan denda setelah disetujui: termin ditandai waived.
     * Denda yang sudah terbayar TIDAK dikembalikan (terpisah dari waiver).
     */
    public function applyPenaltyWaiver(PaymentSchedule $schedule): PaymentSchedule
    {
        return DB::transaction(function () use ($schedule) {
            /** @var PaymentSchedule $locked */
            $locked = PaymentSchedule::query()->lockForUpdate()->findOrFail($schedule->getKey());

            if ($locked->status === PaymentScheduleStatus::Paid) {
                throw new InvalidArgumentException('Termin sudah lunas dan tidak dapat dibebaskan.');
            }

            $locked->status = PaymentScheduleStatus::Waived;
            $locked->notes = trim((string) $locked->notes.' [WAIVED '.now()->format('Y-m-d').']');
            $locked->save();

            return $locked->fresh();
        });
    }

    // ── 29.3 Eskalasi harga ──────────────────────────────────────────────

    /**
     * Hitung faktor eskalasi berdasarkan formula dan indeks tersimpan.
     *
     * Simulasi: formula dievaluasi dengan substitusi terbatas terhadap
     * `base`, `index`, `index_base`. Tidak ada eksekusi kode bebas.
     *
     * @return array{factor: float, current_index: float|null, applied: bool}
     */
    public function computeEscalation(Contract $contract): array
    {
        if (! $contract->escalation_enabled || $contract->escalation_formula === null) {
            return ['factor' => 1.0, 'current_index' => null, 'applied' => false];
        }

        $code = $contract->escalation_index_code;
        $currentIndex = $code !== null ? EscalationIndex::latestValue($code) : null;
        $indexBase = $contract->escalation_index_base;

        if ($currentIndex === null || $indexBase === null || $indexBase <= 0) {
            return ['factor' => 1.0, 'current_index' => $currentIndex, 'applied' => false];
        }

        $factor = $this->evaluateEscalationFormula(
            (string) $contract->escalation_formula,
            $indexBase,
            $currentIndex
        );

        if ($contract->escalation_cap_percent !== null) {
            $maxFactor = 1 + ((float) $contract->escalation_cap_percent / 100);
            $minFactor = 1 - ((float) $contract->escalation_cap_percent / 100);
            $factor = max($minFactor, min($maxFactor, $factor));
        }

        return ['factor' => $factor, 'current_index' => $currentIndex, 'applied' => true];
    }

    /**
     * Evaluasi formula eskalasi terbatas.
     *
     * Substitusi: `index` → nilai indeks saat ini, `index_base` → indeks dasar.
     * Expression dievaluasi dengan parser aritmatika sederhana (hanya angka,
     * operator + - * / ( ) dan variabel yang disebutkan) sehingga tidak ada
     * eksekusi kode (`eval`).
     */
    private function evaluateEscalationFormula(string $formula, float $indexBase, float $currentIndex): float
    {
        $replacements = [
            'index_base' => $indexBase,
            'index' => $currentIndex,
            'base' => 1.0,
        ];

        $expr = strtr($formula, [
            'index_base' => (string) $indexBase,
            'index' => (string) $currentIndex,
            'base' => '1',
        ]);

        // Izinkan hanya karakter angka, operator, titik, spasi, kurung.
        if (! preg_match('/^[0-9+\-*\/().\s]+$/', $expr)) {
            return 1.0;
        }

        // Evaluasi aritmatika aman tanpa eval PHP (sederhana: ekspresi di atas
        // telah dibersihkan sehingga aman untuk evaluasi berbasis parser lokal).
        $result = $this->safeArithmeticEval($expr);

        return $result ?? 1.0;
    }

    /**
     * Evaluasi aritmatika ekspresi bersih berisi angka dan operator dasar.
     * Digunakan untuk formula eskalasi terbatas; tidak menerima ekspresi arbitrer.
     */
    private function safeArithmeticEval(string $expr): ?float
    {
        if (preg_match_all('/(?:\d+(?:\.\d*)?|\.\d+)|[()+\-*\/]/', $expr, $matches) === false) {
            return null;
        }

        $tokens = $matches[0];
        if ($tokens === [] || implode('', $tokens) !== preg_replace('/\s+/', '', $expr)) {
            return null;
        }

        // Formula ini hanya menentukan faktor eskalasi (bukan nilai IDR).
        // Semua amount IDR sesudahnya tetap dihitung integer/BigDecimal.
        return $this->evaluateArithmetic($tokens);
    }

    /**
     * Shunting-yard mini untuk ekspresi aritmatika aman.
     *
     * @param  array<int, string>  $tokens
     */
    private function evaluateArithmetic(array $tokens): ?float
    {
        $output = [];
        $ops = [];

        $prec = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];
        $isNumber = fn (string $t): bool => is_numeric($t);

        foreach ($tokens as $token) {
            if ($isNumber($token)) {
                $output[] = (float) $token;

                continue;
            }

            if ($token === '(') {
                $ops[] = $token;

                continue;
            }

            if ($token === ')') {
                while ($ops !== [] && end($ops) !== '(') {
                    $output[] = array_pop($ops);
                }
                if ($ops === []) {
                    return null;
                }
                array_pop($ops);

                continue;
            }

            if (! isset($prec[$token])) {
                return null;
            }

            while ($ops !== [] && end($ops) !== '(' && isset($prec[end($ops)]) && $prec[end($ops)] >= $prec[$token]) {
                $output[] = array_pop($ops);
            }

            $ops[] = $token;
        }

        while ($ops !== []) {
            $op = array_pop($ops);
            if ($op === '(') {
                return null;
            }
            $output[] = $op;
        }

        $stack = [];
        foreach ($output as $item) {
            if (is_float($item)) {
                $stack[] = $item;

                continue;
            }

            if (count($stack) < 2) {
                return null;
            }

            $b = array_pop($stack);
            $a = array_pop($stack);

            $stack[] = match ($item) {
                '+' => $a + $b,
                '-' => $a - $b,
                '*' => $a * $b,
                '/' => $b == 0.0 ? null : $a / $b,
                default => null,
            };

            if (end($stack) === null) {
                return null;
            }
        }

        return count($stack) === 1 ? $stack[0] : null;
    }

    /**
     * Terapkan eskalasi: bangun ulang jadwal termin nilai baru (29.3).
     *
     * @return array{applied: bool, factor: float, new_total_idr: int}
     */
    public function applyEscalation(Contract $contract): array
    {
        $esc = $this->computeEscalation($contract);

        if (! $esc['applied'] || $esc['factor'] == 1.0) {
            return ['applied' => false, 'factor' => 1.0, 'new_total_idr' => (int) $contract->total_value_idr];
        }

        // Konversi faktor float → string eksplisit: BigDecimal menolak float
        // (aturan invarian uang proyek: tanpa float pada perhitungan uang).
        $factor = BigDecimal::of(sprintf('%.10F', $esc['factor']));
        $newTotal = (int) BigDecimal::of($contract->total_value_idr)
            ->multipliedBy($factor)
            ->toScale(0, RoundingMode::HalfUp)
            ->__toString();

        DB::transaction(function () use ($contract, $newTotal) {
            /** @var Contract $locked */
            $locked = Contract::query()->lockForUpdate()->findOrFail($contract->getKey());
            $locked->total_value_idr = $newTotal;
            $locked->save();

            // Termin yang belum dibayar ikut menyesuaikan proporsional.
            $schedules = $locked->paymentSchedules()->lockForUpdate()->get();
            $unpaid = $schedules->filter(fn (PaymentSchedule $s) => $s->status === PaymentScheduleStatus::Pending);

            if ($unpaid->isEmpty()) {
                return;
            }

            $oldTotal = $schedules->sum('amount_idr');
            $newTotalSched = $schedules->sum('amount_idr') - $unpaid->sum('amount_idr')
                + (int) BigDecimal::of($unpaid->sum('amount_idr'))
                    ->multipliedBy($factor)
                    ->toScale(0, RoundingMode::HalfUp)
                    ->__toString();

            foreach ($unpaid as $schedule) {
                $adjusted = (int) BigDecimal::of($schedule->amount_idr)
                    ->multipliedBy($factor)
                    ->toScale(0, RoundingMode::HalfUp)
                    ->__toString();

                $schedule->amount_idr = $adjusted;
                $schedule->retention_amount_idr = $locked->retention_percent > 0
                    ? (int) BigDecimal::of($adjusted)
                        ->multipliedBy($locked->retention_percent)
                        ->dividedBy(100, 0, RoundingMode::HalfUp)
                        ->__toString()
                    : 0;
                $schedule->save();
            }
        });

        return ['applied' => true, 'factor' => $esc['factor'], 'new_total_idr' => $newTotal];
    }

    /**
     * Ambil RateCard kontrak sebagai precedence hook bagi caller Logistics.
     * Query tetap melalui contract id; tidak mengimpor model Domain Logistics
     * sehingga boundary modul dijaga.
     */
    public function linkedRateCardId(Contract $contract): ?int
    {
        return $contract->linked_rate_card_id === null ? null : (int) $contract->linked_rate_card_id;
    }

    /**
     * Tautan non-breaking ke Mall Lease (tanpa duplikasi atau akses Domain Mall).
     */
    public function linkLease(Contract $contract, string $leaseId): void
    {
        DB::table('ctr_contracts')->where('id', $contract->id)->update(['linked_lease_id' => $leaseId]);
    }

    /** Tautan non-breaking ke OutletContract Resto. */
    public function linkRoyaltyContract(Contract $contract, string $outletContractUuid): void
    {
        DB::table('ctr_contracts')->where('id', $contract->id)->update(['linked_royalty_ref' => $outletContractUuid]);
    }

    private function walletAccountFor(int $userId): LedgerAccount
    {
        $user = User::query()->findOrFail($userId);

        return $user->walletAccount('IDR');
    }
}
