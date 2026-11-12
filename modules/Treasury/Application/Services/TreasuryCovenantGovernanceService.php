<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Finance\Contracts\LoanExposureProvider;
use Modules\Treasury\Domain\Algorithms\FxRevaluationCalculator;
use Modules\Treasury\Domain\Algorithms\RationalRate;
use Modules\Treasury\Domain\Events\TreasuryRevaluationPostedEvent;
use Modules\Treasury\Domain\Exceptions\HedgeMakerCheckerViolationException;
use Modules\Treasury\Domain\Models\CovenantWaiver;
use Modules\Treasury\Domain\Models\CreditFacility;
use Modules\Treasury\Domain\Models\ExchangeRate;
use Modules\Treasury\Domain\Models\FxLot;
use Modules\Treasury\Domain\Models\Revaluation;
use Psr\Clock\ClockInterface;

/**
 * Layanan Tata Kelola Covenant & Agregasi Eksposur Lintas-Modul (D48-7 & D48-11b/d/e).
 *
 * Menangani:
 *   1. Pengajuan, persetujuan Four-Eyes, dan pencabutan keringanan covenant (CovenantWaiver)
 *   2. Akrual bunga fasilitas kredit bank rasional (hari aktual / 365) beserta posting LedgerService
 *   3. Agregasi seluruh sumber pos moneter valas bertag 'treasury.fx_monetary_item_feed'
 *      (Treasury, Procurement, Trade) dan sinkronisasi subledger lot valas & revaluasi PSAK 10
 */
final class TreasuryCovenantGovernanceService
{
    public function __construct(
        private readonly ?ClockInterface $clock = null,
        private readonly ?TreasuryLedgerPoster $ledgerPoster = null,
        private readonly ?TreasuryEventPublisher $eventPublisher = null,
        private readonly ?TreasuryTaskInboxNotifier $taskNotifier = null,
        private readonly ?LoanExposureProvider $exposureProvider = null,
        private readonly ?ApprovalEngineInterface $approvalEngine = null,
    ) {}

    /**
     * Mengajukan keringanan sementara batas DER covenant (Covenant Waiver) untuk fasilitas kredit.
     */
    public function requestCovenantWaiver(
        CreditFacility $facility,
        int $breachedDerPermil,
        int $waivedMaxDerPermil,
        string $validUntilDate,
        string $reason,
        int $requestedByUserId,
        ?string $customWaiverCode = null
    ): CovenantWaiver {
        if ($waivedMaxDerPermil <= (int) $facility->max_der_permil) {
            throw new InvalidArgumentException(
                "Batas DER waiver ({$waivedMaxDerPermil} permil) harus lebih tinggi dari batas covenant standar ({$facility->max_der_permil} permil)."
            );
        }

        if ($breachedDerPermil > $waivedMaxDerPermil) {
            throw new InvalidArgumentException(
                "Rasio DER saat ini ({$breachedDerPermil} permil) masih melampaui plafon waiver yang diajukan ({$waivedMaxDerPermil} permil)."
            );
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Alasan komersial pengajuan covenant waiver wajib diisi.');
        }

        $now = $this->now();
        $code = $customWaiverCode ?: ('WVR-'.$facility->facility_code.'-'.$now->format('Ymd').'-'.str_pad((string) (CovenantWaiver::count() + 1), 3, '0', STR_PAD_LEFT));

        return CovenantWaiver::create([
            'facility_id' => $facility->id,
            'waiver_code' => $code,
            'breached_der_permil' => $breachedDerPermil,
            'waived_max_der_permil' => $waivedMaxDerPermil,
            'valid_until' => $validUntilDate,
            'status' => 'pending',
            'reason' => trim($reason),
            'requested_by' => $requestedByUserId,
        ]);
    }

    /**
     * Menyetujui pengajuan Covenant Waiver dengan penegakan Four-Eyes Segregation of Duties.
     */
    public function approveCovenantWaiver(
        CovenantWaiver $waiver,
        int $approverUserId,
        ?string $approvalReference = null
    ): CovenantWaiver {
        if ($waiver->status !== 'pending') {
            throw new InvalidArgumentException("Covenant waiver {$waiver->waiver_code} sudah berstatus {$waiver->status}.");
        }

        if ($waiver->requested_by !== null && (int) $waiver->requested_by === $approverUserId) {
            throw new HedgeMakerCheckerViolationException(
                "Penyetuju covenant waiver (ID: {$approverUserId}) tidak boleh sama dengan pemohon (Four-Eyes Segregation of Duties)."
            );
        }

        $ref = $approvalReference ?: ('APPR-WVR-'.$this->now()->format('YmdHis').'-'.$approverUserId);

        $waiver->update([
            'status' => 'approved',
            'approved_by' => $approverUserId,
            'approval_reference' => $ref,
        ]);

        return $waiver->fresh() ?? $waiver;
    }

    /**
     * Menghitung dan memosting akrual bunga fasilitas kredit bank untuk jumlah hari berjalan
     * menggunakan aritmetika integer rasional murni (tanpa float):
     *   bunga_idr = (drawn_amount_idr * interest_rate_permil * days) / (1000 * 365)
     */
    public function accrueFacilityInterest(
        CreditFacility $facility,
        int $daysElapsed,
        string $periodOrDate,
        ?string $idempotencyKey = null,
        ?int $userId = null
    ): int {
        if ($daysElapsed <= 0) {
            throw new InvalidArgumentException('Jumlah hari akrual bunga harus lebih besar dari 0.');
        }

        $drawnIdr = (int) $facility->drawn_amount_idr;
        if ($drawnIdr <= 0) {
            return 0;
        }

        $ratePermil = (int) ($facility->interest_rate_permil ?: (int) round(((float) $facility->interest_rate_percent) * 10));
        if ($ratePermil <= 0) {
            return 0;
        }

        // Perhitungan rasional bulat: (drawn * ratePermil * days) / (1000 * 365)
        $interestAmountIdr = (int) intdiv($drawnIdr * $ratePermil * $daysElapsed, 365_000);
        if ($interestAmountIdr <= 0) {
            return 0;
        }

        $poster = $this->ledgerPoster ?? (app()->bound(TreasuryLedgerPoster::class) ? app(TreasuryLedgerPoster::class) : null);
        if ($poster !== null) {
            $key = $idempotencyKey ?: "IDEM:TRS:FAC_INT:{$facility->id}:{$periodOrDate}:{$daysElapsed}";
            $poster->postFacilityInterestAccrual(
                facilityCode: (string) $facility->facility_code,
                interestAmountIdr: $interestAmountIdr,
                periodOrDate: $periodOrDate,
                idempotencyKey: $key,
                facilityId: (string) $facility->id,
                userId: $userId
            );
        }

        return $interestAmountIdr;
    }

    /**
     * Mengumpulkan seluruh pos moneter valuta asing dari modul-modul yang terdaftar pada
     * container tag 'treasury.fx_monetary_item_feed' (Treasury, Procurement, Trade),
     * menyinkronkan subledger trs_fx_lots, dan mengeksekusi revaluasi PSAK 10.
     *
     * @return array{
     *     period: string,
     *     ingested_items_count: int,
     *     sources: list<string>,
     *     revaluations: list<Revaluation>
     * }
     */
    public function aggregateAndRevalueCrossModuleFeeds(
        string $period,
        ?string $asOfDate = null,
        ?int $userId = null
    ): array {
        $date = $asOfDate ?: $this->now()->toDateString();
        $feeds = app()->tagged('treasury.fx_monetary_item_feed');

        $ingestedCount = 0;
        $sources = [];

        return DB::transaction(function () use ($period, $date, $feeds, $userId, &$ingestedCount, &$sources) {
            foreach ($feeds as $feed) {
                foreach ($feed->items($period) as $item) {
                    $module = (string) ($item['source_module'] ?? 'External');
                    $entityRef = (string) ($item['source_entity_id'] ?? 'ITEM');
                    $ccy = strtoupper((string) ($item['currency'] ?? 'USD'));
                    $balanceMinor = (int) ($item['balance_minor'] ?? 0);
                    $carryingIdr = (int) ($item['carrying_functional_idr'] ?? 0);
                    $lotDate = (string) ($item['lot_date'] ?? $date);

                    if ($ccy === 'IDR' || $balanceMinor <= 0 || $carryingIdr <= 0) {
                        continue;
                    }

                    $sources[] = $module;
                    $sourceRef = "{$module}:{$entityRef}";

                    $existingLot = FxLot::where('source_ref', $sourceRef)->first();
                    if ($existingLot === null) {
                        // Hitung kurs historis implisit: carryingIdr * 100 / balanceMinor
                        $rateNum = max(1, $carryingIdr * 100);
                        $rateDen = max(1, $balanceMinor);

                        FxLot::create([
                            'entity_code' => 'HOLDING',
                            'currency' => $ccy,
                            'lot_date' => $lotDate,
                            'original_amount' => $balanceMinor,
                            'remaining_amount' => $balanceMinor,
                            'original_rate_num' => $rateNum,
                            'original_rate_den' => $rateDen,
                            'original_cost_idr' => $carryingIdr,
                            'current_carrying_rate_num' => $rateNum,
                            'current_carrying_rate_den' => $rateDen,
                            'current_carrying_idr' => $carryingIdr,
                            'status' => 'active',
                            'source_ref' => $sourceRef,
                        ]);
                        $ingestedCount++;
                    }
                }
            }

            $calculator = new FxRevaluationCalculator;
            $activeLots = FxLot::where('status', 'active')->get();
            $byCurrency = $activeLots->groupBy('currency');
            $revaluations = [];

            $poster = $this->ledgerPoster ?? (app()->bound(TreasuryLedgerPoster::class) ? app(TreasuryLedgerPoster::class) : null);
            $publisher = $this->eventPublisher ?? (app()->bound(TreasuryEventPublisher::class) ? app(TreasuryEventPublisher::class) : null);

            foreach ($byCurrency as $curr => $lots) {
                $curr = strtoupper((string) $curr);
                $totalForeign = (int) $lots->sum('remaining_amount');
                $totalBook = (int) $lots->sum('current_carrying_idr');

                $rateRecord = ExchangeRate::where('from_currency', $curr)
                    ->where('to_currency', 'IDR')
                    ->whereDate('rate_date', '<=', $date)
                    ->orderByDesc('rate_date')
                    ->first();

                $rate = $rateRecord !== null
                    ? new RationalRate((int) $rateRecord->rate_numerator, (int) $rateRecord->rate_denominator)
                    : new RationalRate(15650, 1);

                $calcRes = $calculator->calculateRevaluation(
                    foreignBalanceMinor: $totalForeign,
                    carryingFunctionalIdr: $totalBook,
                    rate: $rate,
                    currencyDecimals: 2,
                    isAsset: true
                );

                /** @var Revaluation $reval */
                $reval = Revaluation::updateOrCreate(
                    [
                        'period' => $period,
                        'currency' => $curr,
                    ],
                    [
                        'foreign_balance' => $totalForeign,
                        'book_functional_idr' => $totalBook,
                        'revalued_functional_idr' => $calcRes->revaluedFunctionalIdr,
                        'gain_loss_idr' => $calcRes->unrealizedGainLossIdr,
                        'status' => 'completed',
                        'is_reversed' => false,
                        'certificate' => $calcRes->certificate->toArray(),
                    ]
                );

                if ($poster !== null && $calcRes->unrealizedGainLossIdr !== 0) {
                    $idemKey = "IDEM:TRS:AGG_REVAL:{$period}:{$curr}:{$reval->id}";
                    $tx = $poster->postFxRevaluation(
                        period: $period,
                        currency: $curr,
                        gainLossIdr: $calcRes->unrealizedGainLossIdr,
                        idempotencyKey: $idemKey,
                        revaluationId: (string) $reval->id,
                        userId: $userId
                    );
                    $reval->update(['ledger_transaction_id' => $tx->id]);
                }

                if ($publisher !== null) {
                    $publisher->publishRevaluationPosted(new TreasuryRevaluationPostedEvent(
                        revaluationId: (string) $reval->id,
                        period: $period,
                        currency: $curr,
                        foreignBalanceMinor: $totalForeign,
                        bookFunctionalIdr: $totalBook,
                        revaluedFunctionalIdr: $calcRes->revaluedFunctionalIdr,
                        gainLossIdr: $calcRes->unrealizedGainLossIdr,
                        certificateHash: $calcRes->certificate->certificateHash,
                        ledgerTransactionId: $reval->ledger_transaction_id ? (int) $reval->ledger_transaction_id : null,
                        userId: $userId
                    ));
                }

                $revaluations[] = $reval->fresh() ?? $reval;
            }

            return [
                'period' => $period,
                'ingested_items_count' => $ingestedCount,
                'sources' => array_values(array_unique($sources)),
                'revaluations' => $revaluations,
            ];
        });
    }

    private function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }
}
