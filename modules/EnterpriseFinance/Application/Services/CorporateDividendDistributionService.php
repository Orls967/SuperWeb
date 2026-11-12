<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

class CorporateDividendDistributionService
{
    public function __construct(
        protected readonly EnterpriseFinanceLedgerPoster $ledgerPoster,
        protected readonly ?ClockInterface $clock = null
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Memvalidasi Kepatuhan Cadangan Wajib Statuter (UU PT No. 40/2007 Pasal 70) dan Solvabilitas Sebelum Pembagian Dividen.
     *
     * @param  int  $paidInCapitalMinor  Modal disetor dalam minor IDR.
     * @param  int  $currentStatutoryReserveMinor  Saldo cadangan wajib saat ini.
     * @param  int  $netIncomeMinor  Laba bersih tahun berjalan yang tersedia untuk dividen.
     * @param  int  $proposedDividendMinor  Nilai usulan dividen yang akan dibagikan.
     * @return array{
     *     is_eligible_for_distribution: bool,
     *     statutory_reserve_target_minor: int,
     *     required_reserve_allocation_minor: int,
     *     max_distributable_dividend_minor: int,
     *     rejection_reason: string|null
     * }
     */
    public static function evaluateDividendEligibility(
        int $paidInCapitalMinor,
        int $currentStatutoryReserveMinor,
        int $netIncomeMinor,
        int $proposedDividendMinor
    ): array {
        if ($paidInCapitalMinor <= 0 || $netIncomeMinor <= 0 || $proposedDividendMinor <= 0) {
            return [
                'is_eligible_for_distribution' => false,
                'statutory_reserve_target_minor' => 0,
                'required_reserve_allocation_minor' => 0,
                'max_distributable_dividend_minor' => 0,
                'rejection_reason' => 'Perusahaan wajib memiliki laba bersih positif dan modal disetor sah.',
            ];
        }

        // Target cadangan wajib minimal 20% dari modal disetor
        $targetReserve = intdiv($paidInCapitalMinor * 2000, 10000);
        $reserveDeficit = max(0, $targetReserve - $currentStatutoryReserveMinor);

        // Alokasi tahunan minimal 10% laba bersih hingga cadangan wajib tercapai
        $annualMandatoryReserveAllocation = ($reserveDeficit > 0)
            ? min($reserveDeficit, intdiv($netIncomeMinor * 1000, 10000))
            : 0;

        $maxDistributable = max(0, $netIncomeMinor - $annualMandatoryReserveAllocation);

        if ($proposedDividendMinor > $maxDistributable) {
            return [
                'is_eligible_for_distribution' => false,
                'statutory_reserve_target_minor' => $targetReserve,
                'required_reserve_allocation_minor' => $annualMandatoryReserveAllocation,
                'max_distributable_dividend_minor' => $maxDistributable,
                'rejection_reason' => 'Usulan dividen (Rp '.number_format($proposedDividendMinor / 100, 2).') melebihi batas laba bersih pasca alokasi cadangan wajib (Rp '.number_format($maxDistributable / 100, 2).').',
            ];
        }

        return [
            'is_eligible_for_distribution' => true,
            'statutory_reserve_target_minor' => $targetReserve,
            'required_reserve_allocation_minor' => $annualMandatoryReserveAllocation,
            'max_distributable_dividend_minor' => $maxDistributable,
            'rejection_reason' => null,
        ];
    }

    /**
     * Mengeksekusi Deklarasi Pembagian Dividen Resmi Melalui RUPS dan Memposting Jurnal Utang Dividen.
     *
     * @return array{
     *     success: bool,
     *     entity: string,
     *     resolution_number: string,
     *     dividend_declared_minor: int,
     *     voucher_id: string
     * }
     */
    public function declareDividend(
        string $entityCode,
        int $amountMinor,
        string $rupsResolutionNumber,
        int $operatorId
    ): array {
        if (strlen(trim($rupsResolutionNumber)) < 5) {
            throw new InvalidArgumentException('Nomor Akta Keputusan RUPS wajib dicantumkan lengkap.');
        }

        return DB::transaction(function () use ($entityCode, $amountMinor, $rupsResolutionNumber, $operatorId): array {
            $voucher = $this->ledgerPoster->postSubholdingDividend(
                entity: $entityCode,
                amountMinor: $amountMinor,
                idempotencyKey: "IDEM:EF:DIV:{$entityCode}:".md5($rupsResolutionNumber),
                userId: $operatorId
            );

            return [
                'success' => true,
                'entity' => $entityCode,
                'resolution_number' => $rupsResolutionNumber,
                'dividend_declared_minor' => $amountMinor,
                'voucher_id' => (string) $voucher->id,
            ];
        });
    }
}
