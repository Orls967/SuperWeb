<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\ClockInterface;
use Modules\Shared\Domain\Algorithms\Apportionment\LargestRemainder;
use Modules\Syariah\Domain\Exceptions\IdempotencyConflictException;
use Modules\Syariah\Domain\Models\MurabahahContract;
use Modules\Syariah\Domain\Models\MurabahahSchedule;
use Modules\Syariah\Domain\Models\TazirPenalty;
use Modules\Syariah\Ledger\SyariahSubjectAccounts;
use Modules\Syariah\Ledger\SyariahTransactionTypes;
use RuntimeException;

/**
 * Layanan inti Pembiayaan Murabahah Lini 19 Keuangan Syariah (Fase 161, D19-1..D19-5).
 *
 * Invarian:
 * - D19-1: Larangan keras akun bunga (interest/bunga) pada setiap posting syariah.
 * - D19-2: Alur 6 tahap: PROPOSED -> OFFICER_APPROVED -> DPS_APPROVED (four-eyes) -> ASSET_PURCHASED -> ACTIVE -> SETTLED.
 * - D19-3: Posting double-entry untuk pembelian barang, akad jual (piutang + margin ditangguhkan),
 *          pembayaran angsuran + pengakuan margin proporsional kas, serta denda ta'zir ke dana kebajikan.
 * - D19-4: Jadwal angsuran dihitung dengan LargestRemainder (Hamilton) indeks menaik ('01'..'N').
 */
final class MurabahahService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly LargestRemainder $apportioner,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * D19-4 Hitung jadwal angsuran murabahah tanpa bunga dengan LargestRemainder (indeks menaik).
     *
     * @return list<array{installment_no: int, principal_component_idr: int, margin_component_idr: int, installment_amount_idr: int}>
     */
    public function computeInstallmentSchedule(int $costPriceIdr, int $marginAmountIdr, int $tenorMonths): array
    {
        if ($costPriceIdr <= 0) {
            throw new InvalidArgumentException('Biaya perolehan (cost_price_idr) harus lebih besar dari 0.');
        }
        if ($marginAmountIdr < 0) {
            throw new InvalidArgumentException('Margin murabahah tidak boleh negatif.');
        }
        if ($tenorMonths <= 0 || $tenorMonths > 360) {
            throw new InvalidArgumentException('Tenor angsuran harus antara 1 hingga 360 bulan.');
        }

        $weights = [];
        for ($i = 1; $i <= $tenorMonths; $i++) {
            $weights[] = [
                'key' => sprintf('%03d', $i),
                'weight' => 1,
            ];
        }

        $principals = $this->apportioner->apportion($costPriceIdr, $weights);
        $margins = $this->apportioner->apportion($marginAmountIdr, $weights);

        $schedule = [];
        for ($i = 1; $i <= $tenorMonths; $i++) {
            $key = sprintf('%03d', $i);
            $principal = $principals[$key];
            $margin = $margins[$key];
            $schedule[] = [
                'installment_no' => $i,
                'principal_component_idr' => $principal,
                'margin_component_idr' => $margin,
                'installment_amount_idr' => $principal + $margin,
            ];
        }

        return $schedule;
    }

    /**
     * Tahap 1: Pengajuan akad murabahah (PROPOSED).
     */
    public function propose(
        int $customerUserId,
        int $supplierPartyRef,
        string $assetDescription,
        int $costPriceIdr,
        int $marginAmountIdr,
        int $tenorMonths,
        int $proposedByUserId,
        string $idempotencyKey
    ): MurabahahContract {
        $key = $this->requireKey($idempotencyKey);
        $assetDescription = trim($assetDescription);

        if ($costPriceIdr <= 0 || $marginAmountIdr < 0 || $tenorMonths <= 0) {
            throw new InvalidArgumentException('Parameter biaya, margin, dan tenor murabahah tidak valid.');
        }

        return DB::transaction(function () use (
            $customerUserId,
            $supplierPartyRef,
            $assetDescription,
            $costPriceIdr,
            $marginAmountIdr,
            $tenorMonths,
            $proposedByUserId,
            $key
        ) {
            $existing = MurabahahContract::where('idempotency_key', $key)->first();
            if ($existing !== null) {
                $same = (int) $existing->customer_user_id === $customerUserId
                    && (int) $existing->supplier_party_ref === $supplierPartyRef
                    && (int) $existing->cost_price_idr === $costPriceIdr
                    && (int) $existing->margin_amount_idr === $marginAmountIdr
                    && (int) $existing->tenor_months === $tenorMonths;
                if (! $same) {
                    throw IdempotencyConflictException::forKey($key);
                }

                return $existing;
            }

            $totalSellingPrice = $costPriceIdr + $marginAmountIdr;
            $entropy = hash('sha256', "{$key}:{$customerUserId}:{$supplierPartyRef}:{$costPriceIdr}:{$marginAmountIdr}:{$tenorMonths}");
            $code = 'MRB-'.strtoupper(substr($entropy, 0, 12));

            return MurabahahContract::create([
                'contract_code' => $code,
                'customer_user_id' => $customerUserId,
                'supplier_party_ref' => $supplierPartyRef,
                'asset_description' => $assetDescription,
                'cost_price_idr' => $costPriceIdr,
                'margin_amount_idr' => $marginAmountIdr,
                'total_selling_price_idr' => $totalSellingPrice,
                'tenor_months' => $tenorMonths,
                'status' => MurabahahContract::STATUS_PROPOSED,
                'proposed_by_user_id' => $proposedByUserId,
                'idempotency_key' => $key,
            ]);
        });
    }

    /**
     * Tahap 2a: Persetujuan oleh syariah_officer (OFFICER_APPROVED).
     */
    public function approveByOfficer(MurabahahContract $contract, int $officerUserId): MurabahahContract
    {
        return DB::transaction(function () use ($contract, $officerUserId) {
            $locked = MurabahahContract::query()->lockForUpdate()->findOrFail($contract->id);
            if ($locked->status === MurabahahContract::STATUS_OFFICER_APPROVED) {
                return $locked;
            }
            if ($locked->status !== MurabahahContract::STATUS_PROPOSED) {
                throw new RuntimeException("Akad {$locked->contract_code} tidak dalam status PROPOSED.");
            }

            $locked->update([
                'status' => MurabahahContract::STATUS_OFFICER_APPROVED,
                'officer_approved_by_user_id' => $officerUserId,
                'officer_approved_at' => $this->clock->now(),
            ]);

            return $locked;
        });
    }

    /**
     * Tahap 2b: Reviu kepatuhan Dewan Pengawas Syariah / dps_reviewer (four-eyes mandatori -> DPS_APPROVED).
     */
    public function reviewByDps(MurabahahContract $contract, int $dpsReviewerUserId, string $opinionNote): MurabahahContract
    {
        $opinionNote = trim($opinionNote);
        if ($opinionNote === '') {
            throw new InvalidArgumentException('Catatan opini Dewan Pengawas Syariah (DPS) wajib diisi.');
        }

        return DB::transaction(function () use ($contract, $dpsReviewerUserId, $opinionNote) {
            $locked = MurabahahContract::query()->lockForUpdate()->findOrFail($contract->id);
            if ($locked->status === MurabahahContract::STATUS_DPS_APPROVED) {
                return $locked;
            }
            if ($locked->status !== MurabahahContract::STATUS_OFFICER_APPROVED) {
                throw new RuntimeException('Akad harus disetujui syariah_officer terlebih dahulu sebelum reviu DPS.');
            }
            if ((int) $locked->officer_approved_by_user_id === $dpsReviewerUserId || (int) $locked->proposed_by_user_id === $dpsReviewerUserId) {
                throw new RuntimeException('Pelanggaran four-eyes: reviewer DPS tidak boleh orang yang sama dengan pengaju atau penyetuju officer.');
            }

            $locked->update([
                'status' => MurabahahContract::STATUS_DPS_APPROVED,
                'dps_reviewer_user_id' => $dpsReviewerUserId,
                'dps_reviewed_at' => $this->clock->now(),
                'dps_opinion_note' => $opinionNote,
            ]);

            return $locked;
        });
    }

    /**
     * Tahap 3: Bank membeli barang murabahah dari pemasok (ASSET_PURCHASED).
     * Posting: Debit syb:murabahah_asset:IDR, Kredit ap:syb:supplier:{party}:IDR.
     */
    public function purchaseAsset(MurabahahContract $contract, string $idempotencyKey): MurabahahContract
    {
        $key = $this->requireKey($idempotencyKey);

        return DB::transaction(function () use ($contract, $key) {
            $locked = MurabahahContract::query()->lockForUpdate()->findOrFail($contract->id);
            if ($locked->status === MurabahahContract::STATUS_ASSET_PURCHASED) {
                return $locked;
            }
            if ($locked->status !== MurabahahContract::STATUS_DPS_APPROVED || $locked->dps_reviewer_user_id === null) {
                throw new RuntimeException('Barang murabahah belum boleh dibeli sebelum akad lulus reviu DPS (four-eyes).');
            }

            $supplierAp = SyariahSubjectAccounts::supplierPayable((int) $locked->supplier_party_ref);
            $cost = (int) $locked->cost_price_idr;

            $entries = Posting::lines()
                ->debit('syb:murabahah_asset:IDR', $cost, 'IDR')
                ->credit($supplierAp, $cost, 'IDR')
                ->toEntries('IDR');

            $this->postShariahCompliant(new PostingDTO(
                type: SyariahTransactionTypes::SYB_MURABAHAH_PURCHASE->value,
                description: "Pembelian barang murabahah akad {$locked->contract_code}",
                idempotencyKey: "syb:mrb:purchase:{$locked->id}:{$key}",
                entries: $entries,
                referenceType: 'SYB_MURABAHAH_CONTRACT',
                referenceId: (string) $locked->id,
            ));

            $locked->update([
                'status' => MurabahahContract::STATUS_ASSET_PURCHASED,
                'purchased_at' => $this->clock->now(),
            ]);

            return $locked;
        });
    }

    /**
     * Tahap 4 & 5: Penandatanganan akad jual dengan harga = biaya + margin tetap (ACTIVE) & pembentukan jadwal angsuran.
     * Tanpa reviu DPS atau sebelum barang dibeli bank, akad tidak dapat ditandatangani.
     * Posting: Debit syb:murabahah_receivable:{akad}:IDR (biaya + margin),
     *          Kredit syb:murabahah_asset:IDR (biaya) + Kredit syb:deferred_margin:{akad}:IDR (margin).
     */
    public function signContract(MurabahahContract $contract, string $idempotencyKey): MurabahahContract
    {
        $key = $this->requireKey($idempotencyKey);

        return DB::transaction(function () use ($contract, $key) {
            $locked = MurabahahContract::query()->lockForUpdate()->findOrFail($contract->id);
            if ($locked->status === MurabahahContract::STATUS_ACTIVE) {
                return $locked->load('schedules');
            }
            if ($locked->dps_reviewer_user_id === null || $locked->dps_reviewed_at === null) {
                throw new RuntimeException('Akad murabahah tidak dapat ditandatangani tanpa reviu Dewan Pengawas Syariah (DPS).');
            }
            if ($locked->status !== MurabahahContract::STATUS_ASSET_PURCHASED) {
                throw new RuntimeException('Prinsip syariah murabahah: bank wajib membeli barang terlebih dahulu (ASSET_PURCHASED) sebelum menandatangani akad jual.');
            }

            $receivableAcct = SyariahSubjectAccounts::murabahahReceivable((int) $locked->id);
            $deferredMarginAcct = SyariahSubjectAccounts::deferredMargin((int) $locked->id);

            $cost = (int) $locked->cost_price_idr;
            $margin = (int) $locked->margin_amount_idr;
            $sellingPrice = (int) $locked->total_selling_price_idr;

            $lines = Posting::lines()
                ->debit($receivableAcct, $sellingPrice, 'IDR')
                ->credit('syb:murabahah_asset:IDR', $cost, 'IDR');

            if ($margin > 0) {
                $lines->credit($deferredMarginAcct, $margin, 'IDR');
            }

            $this->postShariahCompliant(new PostingDTO(
                type: SyariahTransactionTypes::SYB_MURABAHAH_SIGN->value,
                description: "Penandatanganan akad jual murabahah {$locked->contract_code}",
                idempotencyKey: "syb:mrb:sign:{$locked->id}:{$key}",
                entries: $lines->toEntries('IDR'),
                referenceType: 'SYB_MURABAHAH_CONTRACT',
                referenceId: (string) $locked->id,
            ));

            $computedSchedule = $this->computeInstallmentSchedule($cost, $margin, (int) $locked->tenor_months);
            $signedAt = CarbonImmutable::instance($this->clock->now());

            foreach ($computedSchedule as $row) {
                MurabahahSchedule::create([
                    'contract_id' => $locked->id,
                    'installment_no' => $row['installment_no'],
                    'due_date' => $signedAt->addMonths($row['installment_no'])->toDateString(),
                    'principal_component_idr' => $row['principal_component_idr'],
                    'margin_component_idr' => $row['margin_component_idr'],
                    'installment_amount_idr' => $row['installment_amount_idr'],
                    'paid_amount_idr' => 0,
                    'status' => MurabahahSchedule::STATUS_UNPAID,
                ]);
            }

            $contractHash = hash('sha256', implode(':', [
                $locked->contract_code,
                $locked->customer_user_id,
                $cost,
                $margin,
                $sellingPrice,
                $locked->tenor_months,
                $locked->dps_reviewer_user_id,
            ]));

            $locked->update([
                'status' => MurabahahContract::STATUS_ACTIVE,
                'signed_at' => $signedAt,
                'contract_hash' => $contractHash,
            ]);

            return $locked->load('schedules');
        });
    }

    /**
     * Tahap 6: Pembayaran angsuran murabahah + pengakuan margin proporsional kas diterima.
     * - Angsuran: Debit wallet:user:{nasabah}:IDR -> Kredit syb:murabahah_receivable:{akad}:IDR
     * - Pengakuan margin: Debit syb:deferred_margin:{akad}:IDR -> Kredit syb:margin_income:IDR
     */
    public function payInstallment(
        MurabahahContract $contract,
        int $installmentNo,
        string $idempotencyKey
    ): MurabahahSchedule {
        $key = $this->requireKey($idempotencyKey);

        return DB::transaction(function () use ($contract, $installmentNo, $key) {
            $lockedContract = MurabahahContract::query()->lockForUpdate()->findOrFail($contract->id);

            $schedule = MurabahahSchedule::query()
                ->where('contract_id', $lockedContract->id)
                ->where('installment_no', $installmentNo)
                ->lockForUpdate()
                ->firstOrFail();

            if ($schedule->status === MurabahahSchedule::STATUS_PAID) {
                if ($schedule->payment_idempotency_key === $key) {
                    return $schedule;
                }
                throw new RuntimeException("Angsuran ke-{$installmentNo} pada akad {$lockedContract->contract_code} sudah lunas.");
            }

            if ($lockedContract->status !== MurabahahContract::STATUS_ACTIVE) {
                throw new RuntimeException("Akad {$lockedContract->contract_code} tidak berstatus ACTIVE.");
            }

            $walletAcct = SyariahSubjectAccounts::customerWallet((int) $lockedContract->customer_user_id);
            $receivableAcct = SyariahSubjectAccounts::murabahahReceivable((int) $lockedContract->id);
            $deferredMarginAcct = SyariahSubjectAccounts::deferredMargin((int) $lockedContract->id);

            $installmentAmount = (int) $schedule->installment_amount_idr;
            $marginComponent = (int) $schedule->margin_component_idr;

            // 1. Penerimaan kas angsuran melunasi piutang murabahah
            $this->postShariahCompliant(new PostingDTO(
                type: SyariahTransactionTypes::SYB_MURABAHAH_INSTALLMENT->value,
                description: "Pembayaran angsuran #{$installmentNo} akad {$lockedContract->contract_code}",
                idempotencyKey: "syb:mrb:inst:{$lockedContract->id}:{$installmentNo}:{$key}",
                entries: Posting::lines()
                    ->debit($walletAcct, $installmentAmount, 'IDR')
                    ->credit($receivableAcct, $installmentAmount, 'IDR')
                    ->toEntries('IDR'),
                referenceType: 'SYB_MURABAHAH_SCHEDULE',
                referenceId: (string) $schedule->id,
            ));

            // 2. Pengakuan margin proporsional kas diterima
            if ($marginComponent > 0) {
                $this->postShariahCompliant(new PostingDTO(
                    type: SyariahTransactionTypes::SYB_MURABAHAH_MARGIN_REC->value,
                    description: "Pengakuan margin angsuran #{$installmentNo} akad {$lockedContract->contract_code}",
                    idempotencyKey: "syb:mrb:margin:{$lockedContract->id}:{$installmentNo}:{$key}",
                    entries: Posting::lines()
                        ->debit($deferredMarginAcct, $marginComponent, 'IDR')
                        ->credit('syb:margin_income:IDR', $marginComponent, 'IDR')
                        ->toEntries('IDR'),
                    referenceType: 'SYB_MURABAHAH_SCHEDULE',
                    referenceId: (string) $schedule->id,
                ));
            }

            $now = $this->clock->now();
            $schedule->update([
                'paid_amount_idr' => $installmentAmount,
                'status' => MurabahahSchedule::STATUS_PAID,
                'paid_at' => $now,
                'payment_idempotency_key' => $key,
            ]);

            $newTotalPaid = (int) $lockedContract->total_paid_idr + $installmentAmount;
            $newRecognizedMargin = (int) $lockedContract->recognized_margin_idr + $marginComponent;
            $unpaidRemaining = MurabahahSchedule::query()
                ->where('contract_id', $lockedContract->id)
                ->where('status', MurabahahSchedule::STATUS_UNPAID)
                ->exists();

            $lockedContract->update([
                'total_paid_idr' => $newTotalPaid,
                'recognized_margin_idr' => $newRecognizedMargin,
                'status' => $unpaidRemaining ? MurabahahContract::STATUS_ACTIVE : MurabahahContract::STATUS_SETTLED,
                'settled_at' => $unpaidRemaining ? null : $now,
            ]);

            return $schedule->fresh();
        });
    }

    /**
     * D19-3 Denda keterlambatan (ta'zir): disalurkan ke dana kebajikan syb:qardhul_hasan_fund:IDR (bukan pendapatan bank).
     */
    public function collectTazirPenalty(
        MurabahahContract $contract,
        int $amountIdr,
        string $reason,
        string $idempotencyKey,
        ?int $scheduleId = null
    ): TazirPenalty {
        $key = $this->requireKey($idempotencyKey);
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Nominal denda ta\'zir harus lebih besar dari 0.');
        }

        return DB::transaction(function () use ($contract, $amountIdr, $reason, $key, $scheduleId) {
            $existing = TazirPenalty::where('idempotency_key', $key)->first();
            if ($existing !== null) {
                if ((int) $existing->contract_id !== (int) $contract->id || (int) $existing->amount_idr !== $amountIdr) {
                    throw IdempotencyConflictException::forKey($key);
                }

                return $existing;
            }

            $locked = MurabahahContract::query()->lockForUpdate()->findOrFail($contract->id);
            $walletAcct = SyariahSubjectAccounts::customerWallet((int) $locked->customer_user_id);

            $this->postShariahCompliant(new PostingDTO(
                type: SyariahTransactionTypes::SYB_MURABAHAH_TAZIR->value,
                description: "Denda keterlambatan ta'zir akad {$locked->contract_code} ke dana kebajikan Qardhul Hasan",
                idempotencyKey: "syb:mrb:tazir:{$locked->id}:{$key}",
                entries: Posting::lines()
                    ->debit($walletAcct, $amountIdr, 'IDR')
                    ->credit('syb:qardhul_hasan_fund:IDR', $amountIdr, 'IDR')
                    ->toEntries('IDR'),
                referenceType: 'SYB_MURABAHAH_CONTRACT',
                referenceId: (string) $locked->id,
            ));

            $penalty = TazirPenalty::create([
                'contract_id' => $locked->id,
                'schedule_id' => $scheduleId,
                'customer_user_id' => $locked->customer_user_id,
                'amount_idr' => $amountIdr,
                'reason' => trim($reason),
                'idempotency_key' => $key,
                'collected_at' => $this->clock->now(),
            ]);

            $locked->update([
                'total_tazir_idr' => (int) $locked->total_tazir_idr + $amountIdr,
            ]);

            return $penalty;
        });
    }

    /**
     * Simulasi pengisian saldo dompet IDR nasabah (Wadiah) untuk pengujian & seeder.
     */
    public function fundCustomerWallet(int $userId, int $amountIdr, string $idempotencyKey): void
    {
        $key = $this->requireKey($idempotencyKey);
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Nominal pendanaan dompet harus positif.');
        }

        $wallet = SyariahSubjectAccounts::customerWallet($userId);

        $this->postShariahCompliant(new PostingDTO(
            type: SyariahTransactionTypes::SYB_WADIAH_FUNDING->value,
            description: "Setoran awal dompet Wadiah nasabah #{$userId}",
            idempotencyKey: "syb:wadiah:fund:{$userId}:{$key}",
            entries: Posting::lines()
                ->debit('syb:wadiah_liquidity_source:IDR', $amountIdr, 'IDR')
                ->credit($wallet, $amountIdr, 'IDR')
                ->toEntries('IDR'),
            referenceType: 'SYB_WADIAH_FUNDING',
            referenceId: (string) $userId,
        ));
    }

    /**
     * D19-1 Guardrail anti-riba: tolak setiap posting syariah yang menyentuh akun mengandung kata 'interest' atau 'bunga'.
     */
    public function postShariahCompliant(PostingDTO $dto): void
    {
        foreach ($dto->entries as $entry) {
            self::assertNoInterestAccount((string) ($entry->accountCode ?? ''));
        }

        $this->ledger->post($dto);
    }

    public static function assertNoInterestAccount(string $accountCodeOrName): void
    {
        if (preg_match('/(interest|bunga)/i', $accountCodeOrName) === 1) {
            throw new RuntimeException("Pelanggaran prinsip syariah (D19-1): akun bunga '{$accountCodeOrName}' dilarang keras dalam transaksi syariah.");
        }
    }

    /** @return Collection<int, MurabahahContract> */
    public function listContracts(?int $customerUserId = null, int $limit = 100): Collection
    {
        $query = MurabahahContract::with('schedules')->orderByDesc('id')->limit($limit);
        if ($customerUserId !== null) {
            $query->where('customer_user_id', $customerUserId);
        }

        return $query->get();
    }

    /** @return array{total_contracts: int, active_contracts: int, settled_contracts: int, total_receivable_idr: int, recognized_margin_idr: int, qardhul_hasan_idr: int} */
    public function summary(?int $customerUserId = null): array
    {
        $query = MurabahahContract::query();
        if ($customerUserId !== null) {
            $query->where('customer_user_id', $customerUserId);
        }
        $rows = $query->get();

        return [
            'total_contracts' => $rows->count(),
            'active_contracts' => $rows->where('status', MurabahahContract::STATUS_ACTIVE)->count(),
            'settled_contracts' => $rows->where('status', MurabahahContract::STATUS_SETTLED)->count(),
            'total_receivable_idr' => (int) $rows->where('status', MurabahahContract::STATUS_ACTIVE)
                ->sum(fn (MurabahahContract $c) => max(0, (int) $c->total_selling_price_idr - (int) $c->total_paid_idr)),
            'recognized_margin_idr' => (int) $rows->sum('recognized_margin_idr'),
            'qardhul_hasan_idr' => (int) $rows->sum('total_tazir_idr'),
        ];
    }

    private function requireKey(string $key): string
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 64) {
            throw new InvalidArgumentException('Idempotency-Key wajib (1–64 karakter).');
        }

        return $key;
    }
}
