<?php

declare(strict_types=1);

namespace Modules\Aviation\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Algorithms\CodeshareMileageAccrualEngine;
use Modules\Aviation\Domain\Models\AviationCodeshareAgreement;
use Modules\Aviation\Domain\Models\AviationTicket;
use Modules\Aviation\Integrations\FinanceAviationLedgerService;

class SettleCodeshareProrationAction
{
    public function __construct(
        protected CodeshareMileageAccrualEngine $engine,
        protected FinanceAviationLedgerService $ledgerService
    ) {}

    /**
     * Selesaikan prorasi bagi hasil pendapatan tiket codeshare.
     *
     * @return array{
     *     agreement_id: int,
     *     ticket_id: int,
     *     operating_share_minor_unit: int,
     *     marketing_share_minor_unit: int,
     *     miles_accrued: int,
     *     settlement_reference: string
     * }
     */
    public function execute(int $agreementId, int $ticketId, int $tpmDistance): array
    {
        return DB::transaction(function () use ($agreementId, $ticketId, $tpmDistance) {
            $agreement = AviationCodeshareAgreement::findOrFail($agreementId);
            $ticket = AviationTicket::lockForUpdate()->findOrFail($ticketId);

            if (! $agreement->isActive()) {
                throw new DomainException(sprintf('Perjanjian codeshare [%s] tidak dalam status aktif.', $agreement->agreement_code));
            }

            $grossFare = (int) $ticket->total_price_minor_unit;
            $splitBps = (int) $agreement->revenue_split_bps;
            $marketingBps = 10000 - $splitBps;

            $result = $this->engine->calculateRevenueShareAndMiles(
                $grossFare,
                $tpmDistance,
                'economy_standard',
                'classic',
                $splitBps,
                $marketingBps
            );

            // Posting jurnal akuntansi penyelesaian kliring antar-maskapai
            $settlementRef = sprintf('SETTLE-CS-%s-%d', date('Ymd'), $ticket->id);
            $this->ledgerService->recordCodeshareSettlement(
                $settlementRef,
                $result['operating_share_minor_unit'],
                $result['marketing_share_minor_unit'],
                $agreement->operating_airline_id,
                $agreement->marketing_airline_id
            );

            return [
                'agreement_id' => $agreement->id,
                'ticket_id' => $ticket->id,
                'operating_share_minor_unit' => $result['operating_share_minor_unit'],
                'marketing_share_minor_unit' => $result['marketing_share_minor_unit'],
                'miles_accrued' => $result['miles_accrued'],
                'settlement_reference' => $settlementRef,
            ];
        });
    }
}
