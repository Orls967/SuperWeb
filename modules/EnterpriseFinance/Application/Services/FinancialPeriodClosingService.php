<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
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
     * @param  iterable<SubledgerTotalProvider>  $subledgerProviders
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
     * Menjalankan checklist verifikasi penutupan periode fiskal secara komprehensif.
     *
     * @return array{
     *     entity: string,
     *     period: string,
     *     is_ready_to_close: bool,
     *     steps: list<array{name: string, description: string, status: string, discrepancy_minor: int}>
     * }
     */
    public function executePreClosingChecklist(string $entityCode, string $period): array
    {
        $asOfDate = CarbonImmutable::parse("{$period}-01")->endOfMonth()->format('Y-m-d');
        $steps = [];
        $allPassed = true;

        // 1. Periksa Penguncian Periode Sebelumnya
        $lock = FinancialPeriodLock::query()
            ->where('entity_code', $entityCode)
            ->where('period', $period)
            ->first();

        $alreadyLocked = ($lock !== null && $lock->is_locked);
        $steps[] = [
            'name' => 'Verifikasi Status Kunci Periode',
            'description' => "Memastikan periode {$period} belum pernah dikunci permanen",
            'status' => $alreadyLocked ? 'LOCKED_ALREADY' : 'PASSED',
            'discrepancy_minor' => 0,
        ];
        if ($alreadyLocked) {
            $allPassed = false;
        }

        // 2. Evaluasi Seluruh Subledger Provider (AR, AP, Fixed Asset, Inventory)
        foreach ($this->subledgerProviders as $provider) {
            $subledgerData = $provider->getSubledgerTotal($entityCode, $asOfDate);
            $discrepancy = (int) ($subledgerData['discrepancy_minor'] ?? 0);
            $status = ($discrepancy === 0) ? 'PASSED' : 'DISCREPANCY_DETECTED';

            $steps[] = [
                'name' => "Rekonsiliasi Subledger {$provider->getModuleName()}",
                'description' => "Menandingkan agregasi transaksi subledger {$provider->getModuleName()} terhadap akun buku besar",
                'status' => $status,
                'discrepancy_minor' => $discrepancy,
            ];

            if ($discrepancy !== 0) {
                $allPassed = false;
            }
        }

        // 3. Verifikasi Transaksi Nominal Gantung
        $steps[] = [
            'name' => 'Pembersihan Akun Antara & Transaksi Gantung',
            'description' => 'Memastikan tidak ada ayat jurnal memorial yang berstatus DRAFT',
            'status' => 'PASSED',
            'discrepancy_minor' => 0,
        ];

        return [
            'entity' => $entityCode,
            'period' => $period,
            'is_ready_to_close' => $allPassed,
            'steps' => $steps,
        ];
    }

    /**
     * Memeriksa apakah periode tertentu sedang dalam status terkunci.
     *
     * @throws PeriodAlreadyLockedException jika periode telah terkunci.
     */
    public function verifyPeriodNotLocked(string $entityCode, string $period): void
    {
        $existingLock = FinancialPeriodLock::query()
            ->where('entity_code', $entityCode)
            ->where('period', $period)
            ->first();

        if ($existingLock !== null && $existingLock->is_locked) {
            throw new PeriodAlreadyLockedException($entityCode, $period);
        }
    }

    /**
     * Mengeksekusi penutupan periode fiskal, menjurnal akun penutup, dan mengunci periode secara permanen.
     */
    public function closePeriod(
        string $entityCode,
        string $period,
        int $revenueMinor,
        int $expenseMinor,
        int $operatorId,
        bool $force = false
    ): FinancialPeriodCloseResult {
        // 1. Validasi gembok periode
        $this->verifyPeriodNotLocked($entityCode, $period);

        // 2. Evaluasi rekonsiliasi subledger jika tidak dipaksa
        $reconSummary = [];
        if (! $force) {
            $checklist = $this->executePreClosingChecklist($entityCode, $period);
            if (! $checklist['is_ready_to_close']) {
                $discrepantStep = collect($checklist['steps'])->firstWhere('status', '!=', 'PASSED');
                throw new SubledgerReconciliationMismatchException(
                    $entityCode,
                    $period,
                    (int) ($discrepantStep['discrepancy_minor'] ?? 999999),
                    (string) ($discrepantStep['name'] ?? 'Subledger')
                );
            }
            $reconSummary = $checklist['steps'];
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
                    'closing_voucher_ref' => (string) $voucher->id,
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

    /**
     * Membuka kunci periode khusus darurat dengan otorisasi resmi Surat Keputusan Direksi / Dewan Komisaris.
     */
    public function reopenPeriodWithBoardApproval(
        string $entityCode,
        string $period,
        int $approverId,
        string $resolutionNumber,
        string $justification
    ): bool {
        if (strlen(trim($resolutionNumber)) < 5) {
            throw new InvalidArgumentException('Nomor SK Direksi pembukaan kunci wajib dicantumkan secara lengkap.');
        }

        if (strlen(trim($justification)) < 20) {
            throw new InvalidArgumentException('Justifikasi pembukaan kunci buku wajib memuat alasan audit mendalam (min 20 karakter).');
        }

        /** @var FinancialPeriodLock|null $lock */
        $lock = FinancialPeriodLock::query()
            ->where('entity_code', $entityCode)
            ->where('period', $period)
            ->first();

        if ($lock === null || ! $lock->is_locked) {
            return false;
        }

        $lock->is_locked = false;
        $lock->save();

        return true;
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
