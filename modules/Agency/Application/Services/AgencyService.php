<?php

declare(strict_types=1);

namespace Modules\Agency\Application\Services;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Agency\Domain\Models\Agent;
use Modules\Agency\Domain\Models\AgentContract;
use Modules\Agency\Domain\Models\Attribution;
use Modules\Agency\Domain\Models\CommissionAccrual;
use Modules\Agency\Domain\Models\CommissionScheme;
use Modules\Agency\Domain\Models\Payout;
use Modules\Agency\Domain\Models\PayoutItem;
use Modules\Agency\Domain\Models\Statement;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;

/**
 * Agensi & komisi (Fase 45): agen + hirarki, skema komisi (flat/persen/slab
 * + override upline), atribusi, akrual hold/retur + clawback, payout
 * periodik dengan PPh simulasi, statement, ledger `agy:*`.
 *
 * Konvensi ledger: debit positif, kredit negatif.
 * - Akrual: DR `agy:commission_expense:IDR` / CR `agy:commission_payable:IDR`
 * - Payout: DR `agy:commission_payable:IDR` / CR `clearing:external:IDR`
 * - PPh dipotong: DR `agy:commission_payable:IDR` / CR `tax:withheld:IDR`
 */
class AgencyService
{
    public const ACCT_EXPENSE = 'agy:commission_expense:IDR';

    public const ACCT_PAYABLE = 'agy:commission_payable:IDR';

    public const ACCT_TAX = 'tax:withheld:IDR';

    public const ACCT_CLEARING = 'clearing:external:IDR';

    /** Hari default masa retur sebelum komisi cair. */
    public const DEFAULT_HOLD_DAYS = 30;

    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
        private readonly ApprovalEngineInterface $approvals,
        private readonly Ledger $ledger,
    ) {}

    // ── 45.1 Agen & hirarki ─────────────────────────────────────────────

    /**
     * @param  array{code:string,name:string,kind?:string,parent_id?:string,
     *   party_id?:string,owner_user_id?:int,region_code?:string}  $data
     */
    public function registerAgent(array $data): Agent
    {
        $code = strtoupper($data['code']);
        if (Agent::where('code', $code)->exists()) {
            throw new InvalidArgumentException("Kode agen {$code} sudah dipakai.");
        }

        $kind = $data['kind'] ?? 'sales_agent';
        if (! in_array($kind, Agent::KINDS, true)) {
            throw new InvalidArgumentException('Jenis agen tidak dikenal.');
        }

        if (($data['parent_id'] ?? null) !== null) {
            $parent = Agent::find($data['parent_id']);
            if ($parent === null || $parent->status !== 'active') {
                throw new InvalidArgumentException('Upline harus agen berstatus active.');
            }
        }

        return Agent::create([
            'code' => $code,
            'name' => $data['name'],
            'kind' => $kind,
            'parent_id' => $data['parent_id'] ?? null,
            'party_id' => $data['party_id'] ?? null,
            'owner_user_id' => $data['owner_user_id'] ?? null,
            'region_code' => $data['region_code'] ?? null,
            'status' => 'onboarding',
        ]);
    }

    /** Transisi status agen dengan guard state machine. */
    public function transition(Agent $agent, string $to): Agent
    {
        if (! in_array($to, ['active', 'suspended', 'terminated'], true)) {
            throw new InvalidArgumentException("Status {$to} tidak dikenal.");
        }

        return DB::transaction(function () use ($agent, $to) {
            /** @var Agent $locked */
            $locked = Agent::query()->lockForUpdate()->findOrFail($agent->getKey());
            if ($locked->status === $to) {
                return $locked;
            }

            $allowed = [
                'onboarding' => ['active', 'terminated'],
                'active' => ['suspended', 'terminated'],
                'suspended' => ['active', 'terminated'],
                'terminated' => [],
            ];
            if (! in_array($to, $allowed[$locked->status] ?? [], true)) {
                throw new InvalidArgumentException("Transisi {$locked->status} → {$to} tidak sah.");
            }

            $locked->update(['status' => $to]);

            return $locked;
        });
    }

    // ── 45.2 Kontrak keagenan ───────────────────────────────────────────

    /**
     * @param  array{contract_ref?:string,territory_scope?:string,product_scope?:array,
     *   exclusive?:bool,non_compete?:bool,valid_from:string,valid_until?:string}  $data
     */
    public function createContract(Agent $agent, array $data): AgentContract
    {
        return AgentContract::create([
            'agent_id' => $agent->id,
            'contract_ref' => $data['contract_ref'] ?? null,
            'territory_scope' => $data['territory_scope'] ?? null,
            'product_scope' => $data['product_scope'] ?? null,
            'exclusive' => (bool) ($data['exclusive'] ?? false),
            'non_compete' => (bool) ($data['non_compete'] ?? false),
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'] ?? null,
            'status' => 'active',
        ]);
    }

    // ── 45.3 Skema komisi ───────────────────────────────────────────────

    /**
     * @param  array{code:string,name:string,basis:string,flat_amount_idr?:int,
     *   rate_percent?:float,scope?:string,scope_ref?:string,slabs?:array,
     *   target_amount_idr?:int,bonus_amount_idr?:int,level?:int,
     *   override_rate_percent?:float,valid_from:string,valid_until?:string}  $data
     */
    public function saveScheme(Agent $agent, array $data): CommissionScheme
    {
        if (! in_array($data['basis'], ['flat', 'percent', 'slab', 'target_bonus'], true)) {
            throw new InvalidArgumentException('Dasar komisi tidak dikenal.');
        }

        return CommissionScheme::updateOrCreate(
            ['agent_id' => $agent->id, 'code' => strtoupper($data['code'])],
            [
                'name' => $data['name'],
                'basis' => $data['basis'],
                'flat_amount_idr' => (int) ($data['flat_amount_idr'] ?? 0),
                'rate_percent' => (float) ($data['rate_percent'] ?? 0),
                'scope' => $data['scope'] ?? 'all',
                'scope_ref' => $data['scope_ref'] ?? null,
                'slabs' => $data['slabs'] ?? null,
                'target_amount_idr' => (int) ($data['target_amount_idr'] ?? 0),
                'bonus_amount_idr' => (int) ($data['bonus_amount_idr'] ?? 0),
                'level' => (int) ($data['level'] ?? 0),
                'override_rate_percent' => (float) ($data['override_rate_percent'] ?? 0),
                'valid_from' => $data['valid_from'],
                'valid_until' => $data['valid_until'] ?? null,
                'is_active' => true,
            ]
        );
    }

    // ── 45.4 Atribusi penjualan ─────────────────────────────────────────

    /**
     * Catat atribusi; aturan last_touch menimpa, first_touch mempertahankan
     * yang pertama untuk reference_id yang sama.
     *
     * @param  array{agent_id:string,reference_id:string,source?:string,rule?:string,
     *   touched_at:string,expires_at?:string}  $data
     */
    public function recordAttribution(array $data): Attribution
    {
        $agent = Agent::findOrFail($data['agent_id']);
        if ($agent->status !== 'active') {
            throw new InvalidArgumentException('Atribusi hanya untuk agen active.');
        }

        return DB::transaction(function () use ($data, $agent) {
            $existing = Attribution::where('reference_id', $data['reference_id'])
                ->where('status', 'active')
                ->first();

            $rule = $data['rule'] ?? 'last_touch';
            if ($existing !== null) {
                if ($rule === 'first_touch') {
                    return $existing; // pertahanan atribusi pertama
                }

                // last_touch: pindahkan ke agen baru.
                $existing->update(['agent_id' => $agent->id, 'touched_at' => $data['touched_at']]);
                if (($data['expires_at'] ?? null) !== null) {
                    $existing->update(['expires_at' => $data['expires_at']]);
                }

                return $existing;
            }

            return Attribution::create([
                'agent_id' => $agent->id,
                'source' => $data['source'] ?? 'referral',
                'reference_id' => $data['reference_id'],
                'rule' => $rule,
                'touched_at' => $data['touched_at'],
                'expires_at' => $data['expires_at'] ?? null,
                'status' => 'active',
            ]);
        });
    }

    /** Atribusi sah untuk satu reference (cek expiry). */
    public function resolveAttribution(string $referenceId): ?Attribution
    {
        return Attribution::where('reference_id', $referenceId)
            ->where('status', 'active')
            ->get()
            ->first(fn (Attribution $attr) => ! $attr->isExpired());
    }

    // ── 45.5 Akrual komisi (hold sampai retur lewat) ────────────────────

    /**
     * Hitung komisi dari skema agen level 0 (direct) + override upline.
     * Idempoten per (agent, reference, source_type).
     *
     * @return array{direct: CommissionAccrual, overrides: array<int, CommissionAccrual>}
     */
    public function accrueSale(Agent $agent, string $referenceId, int $baseAmountIdr, int $holdDays, User $actor): CommissionAccrual
    {
        if ($baseAmountIdr <= 0) {
            throw new InvalidArgumentException('Nilai dasar komisi harus lebih besar dari nol.');
        }
        if (! $agent->canEarn()) {
            throw new InvalidArgumentException("Agen {$agent->code} tidak aktif untuk komisi.");
        }

        return DB::transaction(function () use ($agent, $referenceId, $baseAmountIdr, $holdDays, $actor) {
            $holdUntil = now()->addDays(max(0, $holdDays))->toDateString();

            // Idempoten per (agent, reference, sale).
            $existing = CommissionAccrual::where('agent_id', $agent->id)
                ->where('reference_id', $referenceId)
                ->where('source_type', 'sale')
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            $scheme = $this->directScheme($agent, $baseAmountIdr);
            $amount = $scheme !== null ? $scheme->calculate($baseAmountIdr) : 0;
            $rate = $scheme !== null ? (float) $scheme->rate_percent : 0.0;

            if ($amount <= 0) {
                throw new InvalidArgumentException("Tidak ada skema komisi aktif untuk agen {$agent->code}.");
            }

            // Hold 0 hari (masa retur lewat) → langsung payable.
            $accrual = CommissionAccrual::create([
                'agent_id' => $agent->id,
                'reference_id' => $referenceId,
                'source_type' => 'sale',
                'accrued_at' => now()->toDateString(),
                'base_amount_idr' => $baseAmountIdr,
                'rate_percent' => $rate,
                'amount_idr' => $amount,
                'status' => $holdUntil <= now()->toDateString() ? 'payable' : 'hold',
                'hold_until' => $holdUntil,
                'note' => 'Akrual penjualan (hold retur)',
            ]);

            $this->ensureAccounts();
            $this->postCommission($accrual, 'accrue');

            // 45.3 Override upline: agen induk dapat rate override per level.
            $this->accrueUplineOverrides($agent, $referenceId, $baseAmountIdr, $holdUntil, $actor);

            return $accrual;
        });
    }

    private function accrueUplineOverrides(Agent $agent, string $referenceId, int $base, string $holdUntil, User $actor): void
    {
        foreach ($agent->uplines((int) $agent->max_downline_levels) as $depth => $upline) {
            $level = $depth + 1;
            $override = CommissionScheme::where('agent_id', $upline->id)
                ->where('level', $level)
                ->where('is_active', true)
                ->where('basis', 'percent')
                ->whereDate('valid_from', '<=', now()->toDateString())
                ->get()
                ->first(fn (CommissionScheme $s) => $s->isLive());

            if ($override === null) {
                continue;
            }

            $already = CommissionAccrual::where('agent_id', $upline->id)
                ->where('reference_id', $referenceId)
                ->where('source_type', 'override')
                ->exists();
            if ($already) {
                continue;
            }

            $amount = (int) floor($base * (float) $override->override_rate_percent / 100);
            if ($amount <= 0) {
                continue;
            }

            $accrual = CommissionAccrual::create([
                'agent_id' => $upline->id,
                'reference_id' => $referenceId,
                'source_type' => 'override',
                'accrued_at' => now()->toDateString(),
                'base_amount_idr' => $base,
                'rate_percent' => (float) $override->override_rate_percent,
                'amount_idr' => $amount,
                'status' => $holdUntil <= now()->toDateString() ? 'payable' : 'hold',
                'hold_until' => $holdUntil,
                'note' => "Override level {$level} (upline {$upline->code})",
            ]);

            $this->ensureAccounts();
            $this->postCommission($accrual, 'accrue');
        }
    }

    private function directScheme(Agent $agent, int $baseAmountIdr): ?CommissionScheme
    {
        $schemes = CommissionScheme::where('agent_id', $agent->id)
            ->where('level', 0)
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', now()->toDateString())
            ->get()
            ->filter(fn (CommissionScheme $s) => $s->isLive());

        foreach ($schemes as $scheme) {
            if ($scheme->scope === 'all') {
                return $scheme;
            }
        }

        return $schemes->first();
    }

    // ── 45.6 Clawback ───────────────────────────────────────────────────

    /**
     * Balik komisi karena retur/pembatalan; menciptakan akrual negatif
     * (saldo agen bisa negatif → dikompensasi payout berikut).
     */
    public function recordClawback(int $accrualId, int $amountIdr, string $reason, User $actor): CommissionAccrual
    {
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Nilai clawback harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($accrualId, $amountIdr, $reason) {
            $source = CommissionAccrual::query()->lockForUpdate()->findOrFail($accrualId);
            if ($source->status === 'reversed') {
                return $source; // idempoten per akrual sumber
            }

            $agent = Agent::query()->lockForUpdate()->findOrFail($source->agent_id);
            $note = sprintf('Clawback %s: %s', $source->reference_id, $reason);

            $clawback = CommissionAccrual::create([
                'agent_id' => $agent->id,
                'reference_id' => $source->reference_id.':clawback:'.$source->id,
                'source_type' => 'retur',
                'accrued_at' => now()->toDateString(),
                'base_amount_idr' => -$amountIdr,
                'rate_percent' => $source->rate_percent,
                'amount_idr' => -$amountIdr, // negatif = clawback
                'status' => 'payable', // langsung dapat dikompensasi
                'hold_until' => now()->toDateString(),
                'note' => $note,
            ]);

            $source->status = 'reversed';
            $source->save();

            $this->ensureAccounts();
            $this->postCommission($clawback, 'clawback');

            return $clawback;
        });
    }

    /** Lepaskan akrual yang lewat masa retur → status payable. */
    public function releaseHolds(int $retentionDays = self::DEFAULT_HOLD_DAYS): int
    {
        return CommissionAccrual::where('status', 'hold')
            ->where('hold_until', '<=', now()->toDateString())
            ->update(['status' => 'payable']);
    }

    // ── 45.7 Payout periodik (PPh simulasi) ─────────────────────────────

    /**
     * Buat payout dari seluruh akrual payable (termasuk clawback negatif).
     *
     * @return array{payout: Payout, gross: int, withheld: int, net: int}
     */
    public function createPayout(Agent $agent, string $period, float $withholdingRatePercent, User $creator): Payout
    {
        return DB::transaction(function () use ($agent, $period, $withholdingRatePercent, $creator) {
            $existing = Payout::where('agent_id', $agent->id)->where('period', $period)->first();
            if ($existing !== null) {
                return $existing; // idempoten
            }

            $accruals = CommissionAccrual::where('agent_id', $agent->id)
                ->where('status', 'payable')
                ->get();
            if ($accruals->isEmpty()) {
                throw new InvalidArgumentException('Tidak ada akrual payable untuk payout.');
            }

            $gross = (int) $accruals->sum('amount_idr');
            if ($gross <= 0) {
                throw new InvalidArgumentException('Saldo komisi net tidak positif (clawback melebihi kredit).');
            }

            $withheld = (int) floor($gross * max(0, min(100, $withholdingRatePercent)) / 100);
            $net = $gross - $withheld;

            $number = $this->numbering->nextNumber('AGY', 'PAY', false, 'PAY/{ENT}/');
            $payout = Payout::create([
                'agent_id' => $agent->id,
                'number' => $number,
                'period' => $period,
                'gross_idr' => $gross,
                'withheld_tax_idr' => $withheld,
                'net_idr' => $net,
                'status' => 'pending',
                'created_by_user_id' => $creator->id,
            ]);

            foreach ($accruals as $accrual) {
                PayoutItem::create([
                    'payout_id' => $payout->id,
                    'accrual_id' => $accrual->id,
                    'amount_idr' => (int) $accrual->amount_idr,
                ]);
            }

            return $payout->load('items');
        });
    }

    /** Approve payout (four-eyes) → status approved. */
    public function approvePayout(Payout $payout, User $approver): Payout
    {
        return DB::transaction(function () use ($payout, $approver) {
            /** @var Payout $locked */
            $locked = Payout::query()->lockForUpdate()->findOrFail($payout->getKey());
            if ($locked->status === 'approved') {
                return $locked; // idempoten
            }
            if ($locked->status !== 'pending') {
                throw new InvalidArgumentException("Payout {$locked->number} tidak menunggu approval ({$locked->status}).");
            }

            if ($locked->approval_id === null) {
                $creator = User::find($locked->created_by_user_id) ?? $approver;
                $approval = $this->approvals->submit(
                    approvalType: 'AGENCY_PAYOUT',
                    title: "Payout komisi {$locked->number} ({$locked->period})",
                    creator: $creator,
                    approvable: $locked,
                    amount: (float) (int) $locked->net_idr,
                    steps: [['role' => 'procurement'], ['role' => 'admin']],
                    slaHours: 48,
                    metadata: ['payout_id' => $locked->id, 'agent_id' => $locked->agent_id],
                );
                $locked->approval_id = (int) $approval->id;
                $locked->save();
            }

            $approval = $this->approvals->approve((int) $locked->approval_id, $approver, 'Payout disetujui');
            for ($i = 0; $i < 5 && property_exists($approval, 'status') && $approval->status !== 'approved'; $i++) {
                $approval = $this->approvals->approve((int) $locked->approval_id, $approver, 'Payout disetujui');
            }
            if (property_exists($approval, 'status') && $approval->status !== 'approved') {
                throw new InvalidArgumentException('Approval payout belum tuntas.');
            }

            $locked->status = 'approved';
            $locked->save();

            return $locked;
        });
    }

    /** Bayar payout approved → ledger + status paid (idempoten). */
    public function payPayout(Payout $payout, User $payer, ?string $proofReference = null): Payout
    {
        return DB::transaction(function () use ($payout, $proofReference) {
            /** @var Payout $locked */
            $locked = Payout::query()->lockForUpdate()->findOrFail($payout->getKey());
            if ($locked->status === 'paid') {
                return $locked;
            }
            if ($locked->status !== 'approved') {
                throw new InvalidArgumentException("Payout {$locked->number} belum approved ({$locked->status}).");
            }

            $this->ensureAccounts();

            // 1) Bayar net: DR payable / CR clearing.
            $tx = $this->ledger->post(new PostingDTO(
                type: TransactionType::MANUAL_ADJUSTMENT->value,
                description: "Payout komisi {$locked->number} ({$locked->period})",
                idempotencyKey: 'agy:payout:'.$locked->id,
                entries: [
                    PostingEntryDTO::forCode(self::ACCT_PAYABLE, 'IDR', BigDecimal::of((int) $locked->net_idr)),
                    PostingEntryDTO::forCode(self::ACCT_CLEARING, 'IDR', BigDecimal::of((int) $locked->net_idr)->negated()),
                ],
                referenceType: Payout::class,
                referenceId: $locked->id,
                meta: ['agent_id' => $locked->agent_id, 'proof' => $proofReference],
                postedAt: now(),
            ));

            // 2) PPh dipotong (simulasi): DR payable / CR tax withheld.
            if ((int) $locked->withheld_tax_idr > 0) {
                $this->ledger->post(new PostingDTO(
                    type: TransactionType::MANUAL_ADJUSTMENT->value,
                    description: "PPh dipotong payout {$locked->number} (SIMULASI)",
                    idempotencyKey: 'agy:payout:tax:'.$locked->id,
                    entries: [
                        PostingEntryDTO::forCode(self::ACCT_PAYABLE, 'IDR', BigDecimal::of((int) $locked->withheld_tax_idr)),
                        PostingEntryDTO::forCode(self::ACCT_TAX, 'IDR', BigDecimal::of((int) $locked->withheld_tax_idr)->negated()),
                    ],
                    referenceType: Payout::class,
                    referenceId: $locked->id,
                    postedAt: now(),
                ));
            }

            foreach ($locked->items as $item) {
                CommissionAccrual::where('id', $item->accrual_id)
                    ->where('status', 'payable')
                    ->update(['status' => 'paid']);
            }

            $locked->update([
                'status' => 'paid',
                'ledger_transaction_id' => $tx->id,
                'proof_reference' => $proofReference,
                'paid_at' => now(),
            ]);

            return $locked;
        });
    }

    // ── 45.8 Statement ──────────────────────────────────────────────────

    /** Hitung & simpan statement per agent per periode. */
    public function buildStatement(Agent $agent, string $period): Statement
    {
        $accruals = CommissionAccrual::where('agent_id', $agent->id)
            ->whereYear('accrued_at', substr($period, 0, 4))
            ->get();

        $accrued = (int) $accruals->where('amount_idr', '>', 0)->sum('amount_idr');
        $clawback = abs((int) $accruals->where('amount_idr', '<', 0)->sum('amount_idr'));
        $paid = (int) Payout::where('agent_id', $agent->id)
            ->where('period', $period)
            ->where('status', 'paid')
            ->sum('net_idr');
        $opening = (int) Statement::where('agent_id', $agent->id)
            ->where('period', '<', $period)
            ->sum('closing_balance_idr');

        return Statement::updateOrCreate(
            ['agent_id' => $agent->id, 'period' => $period],
            [
                'opening_balance_idr' => $opening,
                'accrued_idr' => $accrued,
                'clawback_idr' => $clawback,
                'paid_idr' => $paid,
                'closing_balance_idr' => $opening + $accrued - $clawback - $paid,
                'breakdown' => [
                    'accruals' => $accruals->count(),
                    'payable' => (int) $accruals->where('status', 'payable')->sum('amount_idr'),
                    'hold' => (int) $accruals->where('status', 'hold')->sum('amount_idr'),
                ],
            ]
        );
    }

    // ── Ledger ───────────────────────────────────────────────────────────

    private function postCommission(CommissionAccrual $accrual, string $kind): void
    {
        $amount = (int) $accrual->amount_idr;
        if ($amount === 0) {
            return;
        }

        // Akrual positif: DR expense / CR payable. Clawback: kebalikan
        // (menurunkan payable → DR payable / CR expense).
        $entries = $amount > 0
            ? [
                PostingEntryDTO::forCode(self::ACCT_EXPENSE, 'IDR', BigDecimal::of($amount)),
                PostingEntryDTO::forCode(self::ACCT_PAYABLE, 'IDR', BigDecimal::of($amount)->negated()),
            ]
            : [
                PostingEntryDTO::forCode(self::ACCT_PAYABLE, 'IDR', BigDecimal::of(abs($amount))),
                PostingEntryDTO::forCode(self::ACCT_EXPENSE, 'IDR', BigDecimal::of(abs($amount))->negated()),
            ];

        $this->ledger->post(new PostingDTO(
            type: TransactionType::MANUAL_ADJUSTMENT->value,
            description: sprintf('Komisi agen %s — %s (%s)', $accrual->agent?->code ?? '', $accrual->reference_id, $kind),
            idempotencyKey: 'agy:commission:'.$accrual->id.':'.$kind,
            entries: $entries,
            referenceType: CommissionAccrual::class,
            referenceId: $accrual->id,
            meta: ['agent_id' => $accrual->agent_id, 'kind' => $kind],
            postedAt: now(),
        ));
    }

    public function ensureAccounts(): void
    {
        foreach ([
            [self::ACCT_EXPENSE, 'Beban Komisi Agen', AccountKind::EXPENSE],
            [self::ACCT_PAYABLE, 'Komisi Payable Agen', AccountKind::LIABILITY],
            [self::ACCT_TAX, 'PPh Dipotong (Simulasi)', AccountKind::LIABILITY],
            [self::ACCT_CLEARING, 'Rekening Kliring Eksternal IDR', AccountKind::CLEARING],
        ] as [$code, $name, $kind]) {
            LedgerAccount::firstOrCreate(
                ['code' => $code, 'asset_code' => 'IDR'],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $name, 'kind' => $kind->value,
                    'allow_negative' => true, 'cached_balance' => '0', 'is_frozen' => false,
                ]
            );
        }
    }
}
