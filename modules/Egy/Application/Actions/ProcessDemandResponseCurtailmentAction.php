<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Carbon\Carbon;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Egy\Domain\Models\DemandResponseEvent;
use Modules\Egy\Ledger\EgyAccounts;
use Modules\Egy\Ledger\EgyTransactionTypes;

/**
 * Aksi Pemrosesan Event Demand-Response (Pemangkasan Beban Fleksibel Sinyal PLN).
 */
final class ProcessDemandResponseCurtailmentAction
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {}

    public function execute(
        string $eventCode,
        string $curtailmentSignalId,
        Carbon $startTime,
        int $targetCurtailmentKw,
        int $achievedCurtailmentKw,
        int $responseTimeSeconds,
        int $incentiveRateIdrPerKw = 25000,
        string $idempotencyKey = ''
    ): DemandResponseEvent {
        $existing = DemandResponseEvent::where('event_code', $eventCode)->first();
        if ($existing !== null) {
            return $existing;
        }

        // Respons dianggap valid/berhasil jika pemangkasan mencapai minimal 80% dan waktu < 15 menit (900 detik)
        $isSuccessful = ($achievedCurtailmentKw >= intdiv($targetCurtailmentKw * 8000, 10000)) && ($responseTimeSeconds <= 900);
        $status = $isSuccessful ? 'completed' : 'failed';

        $incentiveRevenueIdr = 0;
        $ledgerTxId = null;

        if ($isSuccessful) {
            $incentiveRevenueIdr = $achievedCurtailmentKw * $incentiveRateIdrPerKw;

            $incentiveRevenueAccount = 'egy:utility_sales_revenue:IDR'; // Pendapatan jasa fleksibilitas utilitas
            $gridOperatorArAccount = EgyAccounts::customerReceivable('PLN');

            $lines = Posting::lines()
                ->debit($gridOperatorArAccount->code, $incentiveRevenueIdr)
                ->credit($incentiveRevenueAccount, $incentiveRevenueIdr);

            $idempKey = ! empty($idempotencyKey) ? $idempotencyKey : sprintf('DR-CURTAIL:%s', $eventCode);

            $tx = $this->ledgerService->post(new PostingDTO(
                type: EgyTransactionTypes::EGY_GRID_BILLING->value,
                description: sprintf('Insentif Demand-Response PLN Curtailment %s (%d kW)', $eventCode, $achievedCurtailmentKw),
                idempotencyKey: $idempKey,
                entries: $lines->toEntries('IDR'),
                referenceType: 'DEMAND_RESPONSE',
                referenceId: $eventCode,
            ));

            $ledgerTxId = $tx->id;
        }

        return DemandResponseEvent::create([
            'event_code' => $eventCode,
            'site_asset_id' => 1,
            'event_date' => $startTime->toDateString(),
            'peak_window_start' => $startTime,
            'peak_window_end' => (clone $startTime)->addMinutes(60),
            'shifted_load_kwh' => $achievedCurtailmentKw,
            'cost_savings_idr' => $incentiveRevenueIdr,
            'total_reward_idr' => $incentiveRevenueIdr,
            'emission_reduction_g_co2e' => $achievedCurtailmentKw * 870,
            'status' => $status,
            'idempotency_key' => $idempotencyKey,
        ]);
    }
}
