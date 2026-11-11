<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\EnterpriseFinance\Domain\Events\EnterprisePeriodClosed;
use Modules\EnterpriseFinance\Domain\Exceptions\PeriodAlreadyLockedException;
use Modules\EnterpriseFinance\Domain\Exceptions\SubledgerReconciliationMismatchException;
use Modules\EnterpriseFinance\Domain\Models\FinancialPeriodLock;
use Modules\Intercompany\Contracts\SubledgerTotalProvider;
use Psr\Clock\ClockInterface;

class FinancialPeriodClosingService
{
    /**
     * @param iterable<SubledgerTotalProvider> $subledgerProviders
     */
    public function __construct(
        protected readonly EnterpriseFinanceLedgerPoster $ledgerPoster,
        protected readonly iterable $subledgerProviders = [],
        protected readonly ?OutboxBusInterface $outboxBus = null,
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
     * Mengeksekusi checklist penutupan periode dan menjurnal penutupan akun nominal.
     */
    public function closePeriod(
        string $entityCode,
        string $period,
        int $revenueMinor,
        int $expenseMinor,
        int $operatorId,
        bool $force = false
    ): FinancialPeriodCloseResult {
        // 1. Periksa apakah periode telah terkunci sebelumnya
        $existingLock = FinancialPeriodLock::query()
            ->where('entity_code', $entityCode)
            ->where('period', $period)
            ->first();

        if ($existingLock !== null && $existingLock->is_locked) {
            throw new PeriodAlreadyLockedException($entityCode, $period);
        }

        // 2. Evaluasi rekonsiliasi subledger jika tidak di-force
        $reconSummary = [];
        if (! $force) {
            $asOfDate = CarbonImmutable::parse("{$period}-01")->endOfMonth()->format('Y-m-d');
            foreach ($this->subledgerProviders as $provider) {
                $subledgerTotal = $provider->getSubledgerTotal($entityCode, $asOfDate);
                $reconSummary[$provider->getModuleName()] = $subledgerTotal;
                // Jika penyedia subledger melaporkan selisih abnormal (misal metadata flag mismatch)
                if (isset($subledgerTotal['discrepancy_minor']) && (int) $subledgerTotal['discrepancy_minor'] !== 0) {
                    throw new SubledgerReconciliationMismatchException(
                        $entityCode,
                        $period,
                        (int) $subledgerTotal['discrepancy_minor'],
                        $provider->getModuleName()
                    );
                }
            }
        }

        return DB::transaction(function () use ($entityCode, $period, $revenueMinor, $expenseMinor, $operatorId, $reconSummary): FinancialPeriodCloseResult {
            // 3. Posting Jurnal Tutup Buku Berimbang (Revenue -> Income Summary -> Retained Earnings)
            $voucher = $this->ledgerPoster->postPeriodClosing(
                entity: $entityCode,
                period: $period,
                revenueMinor: $revenueMinor,
                expenseMinor: $expenseMinor,
                idempotencyKey: "IDEM:EF:CLOSE:{$entityCode}:{$period}",
                userId: $operatorId
            );

            $netIncomeLossMinor = $revenueMinor - $expenseMinor;

            // 4. Kunci Periode Permanen
            $lock = FinancialPeriodLock::updateOrCreate(
                [
                    'entity_code' => $entityCode,
                    'period' => $period,
                ],
                [
                    'is_locked' => true,
                    'locked_at' => $this->now(),
                    'locked_by' => $operatorId,
                    'closing_voucher_id' => (string) $voucher->id,
                    'subledger_recon_summary' => $reconSummary,
                    'net_income_loss_minor' => $netIncomeLossMinor,
                ]
            );

            // 5. Pancarkan Event Tutup Buku via Spine Outbox
            $event = new EnterprisePeriodClosed(
                entityCode: $entityCode,
                period: $period,
                netIncomeLossMinor: $netIncomeLossMinor,
                closingVoucherId: (string) $voucher->id
            );

            if ($this->outboxBus !== null) {
                $this->outboxBus->record(
                    EnterprisePeriodClosed::EVENT_NAME,
                    $event->toPayload(),
                    "EF:CLOSE:{$entityCode}:{$period}"
                );
            }

            return new FinancialPeriodCloseResult(
                success: true,
                entityCode: $entityCode,
                period: $period,
                closingVoucherId: (string) $voucher->id,
                netIncomeLossMinor: $netIncomeLossMinor,
                isLocked: true
            );
        });
    }
}

final class FinancialPeriodCloseResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $entityCode,
        public readonly string $period,
        public readonly string $closingVoucherId,
        public readonly int $netIncomeLossMinor,
        public readonly bool $isLocked
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }
}

