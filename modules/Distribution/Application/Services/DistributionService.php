<?php

declare(strict_types=1);

namespace Modules\Distribution\Application\Services;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Distribution\Domain\Models\ArInvoice;
use Modules\Distribution\Domain\Models\ArPayment;
use Modules\Distribution\Domain\Models\DistOutlet;
use Modules\Distribution\Domain\Models\Distributor;
use Modules\Distribution\Domain\Models\Scorecard;
use Modules\Distribution\Domain\Models\Security;
use Modules\Distribution\Domain\Models\Target;
use Modules\Distribution\Domain\Models\Territory;
use Modules\Distribution\Domain\Models\TerritoryCoverage;
use Modules\Distribution\Domain\Models\Tier;

/**
 * Jaringan distributor (Fase 42): entitas & hirarki, teritori eksklusif,
 * onboarding/jaminan/limit, piutang (AR) + denda + blokir otomatis,
 * target & tier, outlet sell-out, scorecard.
 *
 * Konvensi ledger (sama seperti modul lain): debit positif, kredit negatif.
 * - Piutang jatuh tempo: DR `dist:receivable:IDR` / CR `dist:ar:{id}:IDR`
 * - Pembayaran: DR `dist:ar:{id}:IDR` / CR `clearing:external:IDR`
 * - Denda: DR `dist:denda:IDR` / CR `dist:ar:{id}:IDR`
 */
class DistributionService
{
    public const ACCT_RECEIVABLE = 'dist:receivable:IDR';

    public const ACCT_AR_PREFIX = 'dist:ar:';

    public const ACCT_DENDA = 'dist:denda:IDR';

    public const ACCT_CLEARING = 'clearing:external:IDR';

    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
        private readonly ApprovalEngineInterface $approvals,
        private readonly Ledger $ledger,
    ) {}

    // ── 42.1 Entitas & hirarki ──────────────────────────────────────────

    /**
     * @param  array{code: string, name: string, kind?: string, parent_id?: string,
     *   party_id?: string, owner_user_id?: int, outlet_code?: string,
     *   payment_terms_days?: int, credit_limit_idr?: int}  $data
     */
    public function registerDistributor(array $data): Distributor
    {
        $code = strtoupper($data['code']);
        if (Distributor::where('code', $code)->exists()) {
            throw new InvalidArgumentException("Kode distributor {$code} sudah dipakai.");
        }

        if (isset($data['parent_id']) && $data['parent_id'] !== null) {
            $parent = Distributor::find($data['parent_id']);
            if ($parent === null) {
                throw new InvalidArgumentException('Distributor induk tidak ditemukan.');
            }
            if ($parent->status !== 'approved') {
                throw new InvalidArgumentException('Hanya distributor approved yang boleh punya sub-distributor.');
            }
        }

        if (! in_array($data['kind'] ?? 'distributor', ['distributor', 'sub_distributor', 'agent', 'dealer'], true)) {
            throw new InvalidArgumentException('Jenis jaringan tidak dikenal.');
        }

        return Distributor::create([
            'code' => $code,
            'name' => $data['name'],
            'kind' => $data['kind'] ?? 'distributor',
            'parent_id' => $data['parent_id'] ?? null,
            'party_id' => $data['party_id'] ?? null,
            'owner_user_id' => $data['owner_user_id'] ?? null,
            'outlet_code' => $data['outlet_code'] ?? null,
            'tier' => 'bronze',
            'status' => 'onboarding',
            'payment_terms_days' => (int) ($data['payment_terms_days'] ?? 30),
            'credit_limit_idr' => (int) ($data['credit_limit_idr'] ?? 0),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    // ── 42.2 Teritori & coverage ────────────────────────────────────────

    /**
     * @param  array{parent_id?: int, code: string, name: string, level?: string}  $data
     */
    public function createTerritory(array $data): Territory
    {
        $code = strtoupper($data['code']);
        if (Territory::where('code', $code)->exists()) {
            throw new InvalidArgumentException("Kode wilayah {$code} sudah dipakai.");
        }

        $level = $data['level'] ?? 'city';
        if (! in_array($level, ['province', 'city', 'district'], true)) {
            throw new InvalidArgumentException('Level wilayah harus province, city, atau district.');
        }

        if (($data['parent_id'] ?? null) !== null) {
            $parent = Territory::find($data['parent_id']);
            if ($parent === null) {
                throw new InvalidArgumentException('Wilayah induk tidak ditemukan.');
            }
            // Hirarki: province → city → district.
            $order = ['province' => 0, 'city' => 1, 'district' => 2];
            if (($order[$level] ?? 0) !== ($order[$parent->level] ?? 0) + 1) {
                throw new InvalidArgumentException("Level {$level} harus tepat satu tingkat di bawah {$parent->level}.");
            }
        } elseif ($level !== 'province') {
            throw new InvalidArgumentException("Wilayah {$level} wajib punya induk.");
        }

        return Territory::create([
            'parent_id' => $data['parent_id'] ?? null,
            'code' => $code,
            'name' => $data['name'],
            'level' => $level,
        ]);
    }

    /**
     * Pasang coverage; deteksi konflik eksklusif (distributor lain sudah
     * menguasai wilayah yang sama secara eksklusif).
     *
     * @return array{coverage: TerritoryCoverage, conflict: ?Territory}
     */
    public function coverTerritory(Distributor $distributor, Territory $territory, bool $exclusive, string $validFrom, ?string $validUntil = null): array
    {
        if ($exclusive) {
            $conflicting = TerritoryCoverage::where('territory_id', $territory->id)
                ->where('exclusive', true)
                ->where('distributor_id', '!=', $distributor->id)
                ->whereNull('valid_until')
                ->orWhere(function ($q) use ($territory, $validFrom, $validUntil) {
                    $q->where('territory_id', $territory->id)
                        ->where('exclusive', true)
                        ->where('valid_from', '<=', $validFrom)
                        ->when($validUntil !== null, fn ($qq) => $qq->where('valid_until', '>=', $validFrom));
                })
                ->first();

            if ($conflicting !== null && (string) $conflicting->distributor_id !== (string) $distributor->id) {
                $other = Distributor::find($conflicting->distributor_id);
                throw new InvalidArgumentException(sprintf(
                    'Konflik teritori: %s sudah eksklusif di %s.',
                    $other?->code ?? 'distributor lain',
                    $territory->name
                ));
            }
        }

        $coverage = TerritoryCoverage::updateOrCreate(
            ['distributor_id' => $distributor->id, 'territory_id' => $territory->id],
            ['exclusive' => $exclusive, 'valid_from' => $validFrom, 'valid_until' => $validUntil]
        );

        return ['coverage' => $coverage, 'conflict' => null];
    }

    /** Daftar konflik eksklusif terbuka (untuk dasbor). */
    public function territoryConflicts(): array
    {
        return TerritoryCoverage::where('exclusive', true)
            ->whereNull('valid_until')
            ->get()
            ->groupBy('territory_id')
            ->filter(fn ($group) => $group->count() > 1)
            ->map(fn ($group, $territoryId) => [
                'territory_id' => (int) $territoryId,
                'territory' => Territory::find((int) $territoryId)?->name,
                'codes' => Distributor::whereIn('id', $group->pluck('distributor_id'))->pluck('code')->all(),
            ])->values()->all();
    }

    // ── 42.3 Onboarding: jaminan & approval ─────────────────────────────

    /**
     * @param  array{kind: string, amount_idr: int, reference?: string,
     *   issued_at?: string, expires_at?: string}  $data
     */
    public function recordSecurity(Distributor $distributor, array $data): Security
    {
        if (! in_array($data['kind'], ['bank_guarantee', 'deposit'], true)) {
            throw new InvalidArgumentException('Jenis jaminan harus bank_guarantee atau deposit.');
        }
        if ((int) $data['amount_idr'] <= 0) {
            throw new InvalidArgumentException('Nilai jaminan harus lebih besar dari nol.');
        }

        return $distributor->securities()->create([
            'kind' => $data['kind'],
            'amount_idr' => (int) $data['amount_idr'],
            'reference' => $data['reference'] ?? null,
            'issued_at' => $data['issued_at'] ?? now()->toDateString(),
            'expires_at' => $data['expires_at'] ?? null,
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /** Ajukan onboarding ke approval (KYB-27 + kontrak-28 dicek manual). */
    public function submitOnboarding(Distributor $distributor, User $creator, array $metadata = []): object
    {
        return DB::transaction(function () use ($distributor, $creator, $metadata) {
            /** @var Distributor $locked */
            $locked = Distributor::query()->lockForUpdate()->findOrFail($distributor->getKey());
            if ($locked->status !== 'onboarding') {
                throw new InvalidArgumentException("Onboarding hanya dari status onboarding (saat ini {$locked->status}).");
            }

            // Wajib ada jaminan aktif sebelum disetujui.
            $activeSecurities = $locked->securities()->where('status', 'active')->count();
            if ($activeSecurities === 0) {
                throw new InvalidArgumentException('Onboarding wajib punya jaminan (bank garansi/deposit) aktif.');
            }

            $approval = $this->approvals->submit(
                approvalType: 'DISTRIBUTOR_ONBOARDING',
                title: "Onboarding {$locked->code} — {$locked->name}",
                creator: $creator,
                approvable: $locked,
                amount: (float) (int) $locked->credit_limit_idr,
                steps: [['role' => 'procurement'], ['role' => 'admin']],
                slaHours: 72,
                metadata: array_merge([
                    'distributor_id' => $locked->id,
                    'credit_limit_idr' => (int) $locked->credit_limit_idr,
                ], $metadata),
            );

            $locked->approval_id = (int) $approval->id;
            $locked->save();

            return $approval;
        });
    }

    /** Setujui onboarding → approved + cap limit kredit (four-eyes). */
    public function approveOnboarding(Distributor $distributor, User $approver, int $creditLimitIdr): Distributor
    {
        if ($creditLimitIdr < 0) {
            throw new InvalidArgumentException('Limit kredit tidak boleh negatif.');
        }

        return DB::transaction(function () use ($distributor, $approver, $creditLimitIdr) {
            /** @var Distributor $locked */
            $locked = Distributor::query()->lockForUpdate()->findOrFail($distributor->getKey());
            if ($locked->status === 'approved') {
                return $locked; // idempoten
            }
            if ($locked->status !== 'onboarding') {
                throw new InvalidArgumentException("Status {$locked->status} tidak dapat disetujui.");
            }
            if ($locked->approval_id === null) {
                throw new InvalidArgumentException('Onboarding belum diajukan ke approval.');
            }
            // Four-eyes: lewati semua step approval (procurement → admin).
            // Guard loop agar aman bila engine menambah step di masa depan.
            $approval = $this->approvals->approve((int) $locked->approval_id, $approver, 'Onboarding distributor disetujui');
            for ($i = 0; $i < 5 && property_exists($approval, 'status') && $approval->status !== 'approved'; $i++) {
                $approval = $this->approvals->approve((int) $locked->approval_id, $approver, 'Onboarding distributor disetujui');
            }
            if (property_exists($approval, 'status') && $approval->status !== 'approved') {
                throw new InvalidArgumentException('Approval onboarding belum tuntas — hubungi approver step berikutnya.');
            }

            $locked->update([
                'status' => 'approved',
                'credit_limit_idr' => $creditLimitIdr,
                'approved_at' => now()->toDateString(),
            ]);

            return $locked;
        });
    }

    /** Transisi status (suspend/blokir/terminate) dengan guard. */
    public function transition(Distributor $distributor, string $to): Distributor
    {
        if (! in_array($to, Distributor::STATUSES, true)) {
            throw new InvalidArgumentException("Status {$to} tidak dikenal.");
        }

        return DB::transaction(function () use ($distributor, $to) {
            /** @var Distributor $locked */
            $locked = Distributor::query()->lockForUpdate()->findOrFail($distributor->getKey());
            if ($locked->status === $to) {
                return $locked;
            }

            $allowed = [
                'onboarding' => ['approved', 'terminated'],
                'approved' => ['suspended', 'blocked', 'terminated'],
                'suspended' => ['approved', 'blocked', 'terminated'],
                'blocked' => ['approved', 'terminated'],
                'terminated' => [],
            ];
            if (! in_array($to, $allowed[$locked->status] ?? [], true)) {
                throw new InvalidArgumentException("Transisi {$locked->status} → {$to} tidak sah.");
            }

            $locked->update(['status' => $to]);

            return $locked;
        });
    }

    // ── 42.4 Kredit & piutang ───────────────────────────────────────────

    /**
     * Terbitkan tagihan distributor (penjualan/denda/manual) + jurnal.
     */
    public function issueInvoice(
        Distributor $distributor,
        int $amountIdr,
        string $invoiceDate,
        string $dueDate,
        User $creator,
        string $sourceType = 'sales',
        ?string $sourceRef = null,
        ?string $notes = null,
    ): ArInvoice {
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Nilai tagihan harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($distributor, $amountIdr, $invoiceDate, $dueDate, $creator, $sourceType, $sourceRef, $notes) {
            /** @var Distributor $locked */
            $locked = Distributor::query()->lockForUpdate()->findOrFail($distributor->getKey());
            if ($locked->status !== 'approved') {
                throw new InvalidArgumentException("Tagihan hanya untuk distributor approved ({$locked->status}).");
            }

            $number = $this->numbering->nextNumber('DIST', 'AR', false, 'AR/{ENT}/');
            $invoice = ArInvoice::create([
                'number' => $number,
                'distributor_id' => $locked->id,
                'source_type' => $sourceType,
                'source_ref' => $sourceRef,
                'amount_idr' => $amountIdr,
                'paid_amount_idr' => 0,
                'denda_idr' => 0,
                'status' => 'open',
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'notes' => $notes,
                'created_by_user_id' => $creator->id,
            ]);

            // Exposure bertambah + jurnal DR receivable / CR AR per invoice.
            $locked->credit_exposure_idr = (int) $locked->credit_exposure_idr + $amountIdr;
            // 42.4 Blokir otomatis: eksposur melewati limit kredit.
            if ((int) $locked->credit_limit_idr > 0
                && (int) $locked->credit_exposure_idr > (int) $locked->credit_limit_idr) {
                $locked->status = 'blocked';
            }
            $locked->save();

            $this->postAr($invoice, $amountIdr, 'issued');

            return $invoice;
        });
    }

    /** Bayar tagihan (sebagian/penuh) → reduksi exposure; lewat limit → blokir. */
    public function payInvoice(ArInvoice $invoice, int $amountIdr, string $paidAt, User $collector, string $method = 'transfer', ?string $reference = null): ArInvoice
    {
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Nilai pembayaran harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($invoice, $amountIdr, $paidAt, $method, $reference) {
            /** @var ArInvoice $locked */
            $locked = ArInvoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
            if ($locked->status === 'void') {
                throw new InvalidArgumentException('Tagihan dibatalkan.');
            }

            $open = $locked->openAmount();
            if ($amountIdr > $open) {
                throw new InvalidArgumentException("Pembayaran melebihi sisa tagihan (sisa {$open}).");
            }

            $locked->paid_amount_idr = (int) $locked->paid_amount_idr + $amountIdr;
            $paidAll = $locked->openAmount() <= 0;
            $lockDenda = (int) $locked->denda_idr;
            $locked->status = $paidAll ? 'paid' : 'partial';
            if ($paidAll) {
                $locked->denda_idr = 0; // lunas → denda ikut tercatat beres
            }
            $locked->save();

            ArPayment::create([
                'invoice_id' => $locked->id,
                'amount_idr' => $amountIdr,
                'method' => $method,
                'reference' => $reference,
                'paid_at' => $paidAt,
            ]);

            $distributor = Distributor::query()->lockForUpdate()->findOrFail($locked->distributor_id);
            $distributor->credit_exposure_idr = max(0, (int) $distributor->credit_exposure_idr - $amountIdr);
            if ($paidAll && $lockDenda > 0) {
                $distributor->credit_exposure_idr = max(0, (int) $distributor->credit_exposure_idr - $lockDenda);
            }
            // 42.4: pelunasan menurunkan eksposur — keluar dari blokir bila
            // sekarang berada di bawah limit.
            if ($distributor->status === 'blocked'
                && (int) $distributor->credit_exposure_idr < (int) $distributor->credit_limit_idr) {
                $distributor->status = 'approved';
            }
            $distributor->save();

            // Jurnal: DR AR / CR clearing.
            $this->postAr($locked, $amountIdr, 'payment');

            return $locked->fresh();
        });
    }

    /**
     * Terapkan denda keterlambatan (simulasi % per hari, default 0.1%/hari
     * dari sisa tagihan, dibulatkan ke atas, maksimal 5% dari tagihan).
     */
    public function applyLateFee(ArInvoice $invoice, float $dailyRatePercent = 0.1, float $capPercent = 5.0): ArInvoice
    {
        return DB::transaction(function () use ($invoice, $dailyRatePercent, $capPercent) {
            /** @var ArInvoice $locked */
            $locked = ArInvoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if (! $locked->isOverdue() || $locked->status === 'paid') {
                return $locked;
            }

            $days = $locked->overdueDays();
            $open = $locked->openAmount();
            $cap = (int) ceil((int) $locked->amount_idr * $capPercent / 100);
            $fee = (int) ceil($open * ($dailyRatePercent / 100) * $days);
            $fee = min($fee, $cap - (int) $locked->denda_idr);

            if ($fee <= 0) {
                $locked->status = 'overdue';
                $locked->save();

                return $locked;
            }

            $locked->denda_idr = (int) $locked->denda_idr + $fee;
            $locked->status = 'overdue';
            $locked->save();

            // Denda menambah piutang → exposure ikut naik (sinkron dist:audit
            // yang memakai amount + denda − paid).
            $distributor = Distributor::query()->lockForUpdate()->findOrFail($locked->distributor_id);
            $distributor->credit_exposure_idr = (int) $distributor->credit_exposure_idr + $fee;
            if ((int) $distributor->credit_limit_idr > 0
                && (int) $distributor->credit_exposure_idr > (int) $distributor->credit_limit_idr) {
                $distributor->status = 'blocked';
            }
            $distributor->save();

            // Jurnal denda: DR piutang / CR pendapatan denda (kredit negatif).
            $this->ensureAccounts();
            $this->ledger->post(new PostingDTO(
                type: TransactionType::MANUAL_ADJUSTMENT->value,
                description: "Denda keterlambatan {$locked->number} ({$days} hari)",
                idempotencyKey: 'dist:denda:'.$locked->id.':'.$locked->denda_idr,
                entries: [
                    PostingEntryDTO::forCode(self::ACCT_RECEIVABLE, 'IDR', BigDecimal::of($fee)),
                    PostingEntryDTO::forCode(self::ACCT_DENDA, 'IDR', BigDecimal::of($fee)->negated()),
                ],
                referenceType: ArInvoice::class,
                referenceId: $locked->id,
                postedAt: now(),
            ));

            return $locked;
        });
    }

    /** Sweep harian: tagihan lewat jatuh tempo → overdue + denda;
     *  distributor terlambat lewat termin + grace 7 hari → blokir. */
    public function sweepOverdue(): int
    {
        $count = 0;
        foreach (ArInvoice::whereIn('status', ['open', 'partial'])->where('due_date', '<', now()->toDateString())->get() as $invoice) {
            $before = (int) $invoice->denda_idr;
            $this->applyLateFee($invoice);
            if ((int) $invoice->fresh()->denda_idr !== $before) {
                $count++;
            }

            // Blokir otomatis: overdue > grace 7 hari.
            $fresh = $invoice->fresh();
            if ($fresh !== null && $fresh->overdueDays() > 7) {
                $distributor = Distributor::find($fresh->distributor_id);
                if ($distributor !== null && $distributor->status === 'approved') {
                    $distributor->update(['status' => 'blocked']);
                }
            }
        }

        return $count;
    }

    /** Aging per distributor: 0–30, 31–60, 61–90, >90 hari. */
    public function agingReport(?Distributor $distributor = null): array
    {
        $buckets = ['0-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];

        $query = ArInvoice::whereIn('status', ['open', 'partial', 'overdue'])
            ->whereColumn('paid_amount_idr', '<', 'amount_idr');
        if ($distributor !== null) {
            $query->where('distributor_id', $distributor->id);
        }

        foreach ($query->get() as $invoice) {
            $age = (int) $invoice->invoice_date->diffInDays(now());
            $bucket = $age <= 30 ? '0-30' : ($age <= 60 ? '31-60' : ($age <= 90 ? '61-90' : '90+'));
            $buckets[$bucket] += $invoice->openAmount();
        }

        return $buckets;
    }

    // ── 42.5 Target, capaian & tier ─────────────────────────────────────

    /**
     * @param  array{product_sku: string, period: string, target_qty: float,
     *   basis?: string}  $data
     */
    public function setTarget(Distributor $distributor, array $data): Target
    {
        if (! in_array($data['basis'] ?? 'sell_in', ['sell_in', 'sell_out'], true)) {
            throw new InvalidArgumentException('Basis target harus sell_in atau sell_out.');
        }

        return Target::updateOrCreate(
            [
                'distributor_id' => $distributor->id,
                'product_sku' => strtoupper($data['product_sku']),
                'period' => $data['period'],
                'basis' => $data['basis'] ?? 'sell_in',
            ],
            ['target_qty' => (float) $data['target_qty']]
        );
    }

    /** Catat capaian (idempoten: tumpuk qty, bukan timpa). */
    public function recordAchievement(Target $target, float $qty): Target
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Capaian harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($target, $qty) {
            /** @var Target $locked */
            $locked = Target::query()->lockForUpdate()->findOrFail($target->getKey());
            $locked->achieved_qty = (float) $locked->achieved_qty + $qty;
            $locked->save();

            return $locked;
        });
    }

    /**
     * Evaluasi tier dari rata-rata capaian periode berjalan.
     * Bronze → Silver (≥ min achievement silver) → Gold (≥ gold).
     *
     * @return array{distributor: Distributor, tier: string, changed: bool}
     */
    public function evaluateTier(Distributor $distributor): array
    {
        $targets = $distributor->targets()->where('period', now()->format('Y'))->get();
        $achievement = 0.0;
        if ($targets->count() > 0) {
            $achievement = $targets->avg(fn (Target $t) => $t->achievementPercent());
        }

        $gold = Tier::where('code', 'gold')->first();
        $silver = Tier::where('code', 'silver')->first();
        $newTier = 'bronze';
        if ($gold !== null && $achievement >= (float) $gold->min_achievement_percent) {
            $newTier = 'gold';
        } elseif ($silver !== null && $achievement >= (float) $silver->min_achievement_percent) {
            $newTier = 'silver';
        }

        $changed = $newTier !== $distributor->tier;
        if ($changed) {
            $distributor->update(['tier' => $newTier]);
        }

        return ['distributor' => $distributor->fresh(), 'tier' => $newTier, 'changed' => $changed];
    }

    /** Diskon hak tier saat ini (baca dari tabel dist_tiers). */
    public function tierDiscountPercent(string $tier): float
    {
        return (float) (Tier::where('code', $tier)->value('discount_percent') ?? 0);
    }

    // ── 42.7 Outlet (sell-out) ──────────────────────────────────────────

    /**
     * @param  array{code: string, name: string, segment?: string, city?: string,
     *   address?: string, territory_id?: int}  $data
     */
    public function addOutlet(Distributor $distributor, array $data): DistOutlet
    {
        $code = strtoupper($data['code']);
        if ($distributor->outlets()->where('code', $code)->exists()) {
            throw new InvalidArgumentException("Kode outlet {$code} sudah dipakai distributor ini.");
        }

        if (! in_array($data['segment'] ?? 'retail', ['retail', 'horeca', 'modern', 'wholesale'], true)) {
            throw new InvalidArgumentException('Segment outlet tidak dikenal.');
        }

        if (($data['territory_id'] ?? null) !== null) {
            $coverage = TerritoryCoverage::where('distributor_id', $distributor->id)
                ->where('territory_id', $data['territory_id'])->exists();
            if (! $coverage) {
                throw new InvalidArgumentException('Outlet di wilayah yang belum dicover distributor ini.');
            }
        }

        return $distributor->outlets()->create([
            'code' => $code,
            'name' => $data['name'],
            'segment' => $data['segment'] ?? 'retail',
            'city' => $data['city'] ?? null,
            'address' => $data['address'] ?? null,
            'territory_id' => $data['territory_id'] ?? null,
            'is_active' => true,
        ]);
    }

    // ── 42.8 Scorecard ──────────────────────────────────────────────────

    /**
     * Hitung scorecard: fill rate, DSO, kepatuhan harga, capaian target.
     * Skor komposit 0–100: achievement 40 + fill 20 + DSO 20 + price 20.
     *
     * @param  array{sell_in_idr?: int, sell_out_idr?: int, fill_rate_percent?: float,
     *   price_compliance_percent?: float}  $metrics
     */
    public function computeScorecard(Distributor $distributor, string $period, array $metrics = []): Scorecard
    {
        return DB::transaction(function () use ($distributor, $period, $metrics) {
            $targets = $distributor->targets()->where('period', $period)->get();
            $achievement = $targets->isEmpty()
                ? 0.0
                : (float) $targets->avg(fn (Target $t) => $t->achievementPercent());

            $fillRate = (float) ($metrics['fill_rate_percent'] ?? 100.0);
            $priceCompliance = (float) ($metrics['price_compliance_percent'] ?? 100.0);

            // DSO: rata-rata umur piutang terbuka.
            $openInvoices = $distributor->arInvoices()
                ->whereIn('status', ['open', 'partial', 'overdue'])->get();
            $dso = 0.0;
            if ($openInvoices->isNotEmpty()) {
                $totalOpen = $openInvoices->sum(fn (ArInvoice $i) => $i->openAmount());
                $dso = $totalOpen > 0
                    ? (float) $openInvoices->avg(fn (ArInvoice $i) => (int) $i->invoice_date->diffInDays(now()))
                    : 0.0;
            }

            // DSO makin kecil makin baik; skor DSO = 0 jika >90 hari.
            $dsoScore = $dso <= 0 ? 20.0 : max(0.0, 20.0 * (1 - min($dso, 90) / 90));

            $score = min(100.0, max(0.0,
                min($achievement, 100) * 0.40
                + min($fillRate, 100) * 0.20
                + $dsoScore
                + min($priceCompliance, 100) * 0.20
            ));

            $recommended = $score >= 80 ? 'gold' : ($score >= 60 ? 'silver' : 'bronze');

            return Scorecard::updateOrCreate(
                ['distributor_id' => $distributor->id, 'period' => $period],
                [
                    'sell_in_idr' => (int) ($metrics['sell_in_idr'] ?? 0),
                    'sell_out_idr' => (int) ($metrics['sell_out_idr'] ?? 0),
                    'fill_rate_percent' => $fillRate,
                    'dso_days' => round($dso, 2),
                    'price_compliance_percent' => $priceCompliance,
                    'achievement_percent' => round($achievement, 4),
                    'score' => round($score, 4),
                    'recommended_tier' => $recommended,
                ]
            );
        });
    }

    // ── Ledger AR ───────────────────────────────────────────────────────

    private function postAr(ArInvoice $invoice, int $amountIdr, string $kind): void
    {
        $this->ensureAccounts();

        $arCode = self::ACCT_AR_PREFIX.$invoice->id.':IDR';

        $entries = match ($kind) {
            // Tagihan: DR piutang, CR AR distributor (kredit negatif).
            'issued' => [
                PostingEntryDTO::forCode(self::ACCT_RECEIVABLE, 'IDR', BigDecimal::of($amountIdr)),
                PostingEntryDTO::forCode($arCode, 'IDR', BigDecimal::of($amountIdr)->negated()),
            ],
            // Bayar: DR AR, CR clearing.
            'payment' => [
                PostingEntryDTO::forCode($arCode, 'IDR', BigDecimal::of($amountIdr)),
                PostingEntryDTO::forCode(self::ACCT_CLEARING, 'IDR', BigDecimal::of($amountIdr)->negated()),
            ],
            default => throw new InvalidArgumentException('Jenis posting AR tidak dikenal.'),
        };

        $this->ledger->post(new PostingDTO(
            type: TransactionType::SUPPLIER_PAYABLE->value,
            description: sprintf('AR distributor %s — %s (%s)', $invoice->number, $invoice->distributor?->code ?? '', $kind),
            idempotencyKey: "dist:ar:{$invoice->id}:{$kind}:{$amountIdr}",
            entries: $entries,
            referenceType: ArInvoice::class,
            referenceId: $invoice->id,
            meta: ['distributor_id' => $invoice->distributor_id, 'kind' => $kind],
            postedAt: now(),
        ));
    }

    public function ensureAccounts(): void
    {
        foreach ([
            [self::ACCT_RECEIVABLE, 'Piutang Distributor', AccountKind::ASSET],
            [self::ACCT_DENDA, 'Pendapatan Denda Keterlambatan', AccountKind::REVENUE],
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

        // Akun per invoice dibuat lazy saat postAr.
        foreach (ArInvoice::where('status', '!=', 'void')->pluck('id') as $invoiceId) {
            $code = self::ACCT_AR_PREFIX.$invoiceId.':IDR';
            LedgerAccount::firstOrCreate(
                ['code' => $code, 'asset_code' => 'IDR'],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => 'AR Distributor '.$invoiceId,
                    'kind' => AccountKind::AP->value,
                    'allow_negative' => true, 'cached_balance' => '0', 'is_frozen' => false,
                ]
            );
        }
    }
}
