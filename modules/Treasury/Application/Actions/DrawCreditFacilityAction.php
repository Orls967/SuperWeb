<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Finance\Contracts\LoanExposureProvider;
use Modules\Treasury\Application\Services\TreasuryLedgerPoster;
use Modules\Treasury\Domain\Models\CreditFacility;
use Modules\Treasury\Domain\Models\CreditFacilityDrawdown;
use Psr\Clock\ClockInterface;

class DrawCreditFacilityAction
{
    public function __construct(
        protected readonly ?LoanExposureProvider $exposureProvider = null,
        protected readonly ?TreasuryLedgerPoster $ledgerPoster = null,
        protected readonly ?ClockInterface $clock = null,
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Penarikan plafon pinjaman kredit bank dengan pengujian covenant Debt-to-Equity Ratio (DER).
     *
     * @return array{
     *     facility: CreditFacility,
     *     drawn_amount: int,
     *     covenant_breached: bool,
     *     warning: ?string,
     *     drawdown: CreditFacilityDrawdown
     * }
     */
    public function execute(
        CreditFacility $facility,
        int $amountIdr,
        int|float|null $manualDer = null,
        string $destinationAccountCode = 'trs:bank_operational:HOLDING:IDR',
        ?int $userId = null
    ): array {
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Nominal penarikan fasilitas kredit harus lebih besar dari 0.');
        }

        if ($facility->status !== 'active') {
            throw new InvalidArgumentException("Fasilitas kredit {$facility->facility_code} tidak aktif.");
        }

        if ($facility->drawn_amount_idr + $amountIdr > $facility->credit_limit_idr) {
            throw new InvalidArgumentException('Penarikan melampaui plafon kredit yang disetujui.');
        }

        return DB::transaction(function () use ($facility, $amountIdr, $manualDer, $destinationAccountCode, $userId) {
            $now = $this->now();

            // 1. Hitung total utang dan ekuitas via LoanExposureProvider
            $debtIdr = 0;
            $equityIdr = 1_000_000_000_000; // Default baseline ekuitas holding 1 T

            if ($this->exposureProvider !== null) {
                $debtIdr = $this->exposureProvider->getGroupTotalDebtIdr();
                $equityIdr = max(1, $this->exposureProvider->getGroupTotalEquityIdr());
            }

            // Tambahkan nominal draw yang diajukan ke total utang
            $projectedDebtIdr = $debtIdr + $facility->drawn_amount_idr + $amountIdr;

            // 2. Evaluasi DER permil (integer rasional murni, tanpa float)
            $maxDerPermil = (int) ($facility->max_der_permil ?: (int) round(((float) $facility->max_debt_equity_ratio) * 1000));

            $derPermil = 0;
            if ($manualDer !== null) {
                $derPermil = is_float($manualDer) ? (int) round($manualDer * 1000) : (int) $manualDer;
            } else {
                $derPermil = (int) intdiv($projectedDebtIdr * 1000, $equityIdr);
            }

            $covenantBreached = $derPermil > $maxDerPermil;

            // 3. Update saldo terpakai pada fasilitas
            $facility->increment('drawn_amount_idr', $amountIdr);

            // 4. Catat riwayat penarikan
            /** @var CreditFacilityDrawdown $drawdown */
            $drawdown = CreditFacilityDrawdown::create([
                'facility_id' => $facility->id,
                'entity_id' => $facility->entity_id ?? 'HOLDING',
                'draw_date' => $now->toDateString(),
                'amount_idr' => $amountIdr,
                'covenant_der_permil' => $derPermil,
                'max_der_permil' => $maxDerPermil,
                'verified_debt_idr' => $projectedDebtIdr,
                'verified_equity_idr' => $equityIdr,
                'created_by' => $userId,
            ]);

            // 5. Posting ke ledger
            if ($this->ledgerPoster !== null) {
                $idempotencyKey = "IDEM:TRS:FACILITY_DRAW:{$drawdown->id}";
                $tx = $this->ledgerPoster->postFacilityDrawdown(
                    facilityCode: $facility->facility_code,
                    amountIdr: $amountIdr,
                    destinationAccountCode: $destinationAccountCode,
                    idempotencyKey: $idempotencyKey,
                    facilityId: $facility->id,
                    userId: $userId
                );
                $drawdown->update(['ledger_tx_id' => $tx->id]);
            }

            return [
                'facility' => $facility->fresh(),
                'drawn_amount' => $amountIdr,
                'covenant_breached' => $covenantBreached,
                'warning' => $covenantBreached ? 'Peringatan: Rasio DER melampaui batas covenant bank!' : null,
                'drawdown' => $drawdown->fresh(),
            ];
        });
    }
}
