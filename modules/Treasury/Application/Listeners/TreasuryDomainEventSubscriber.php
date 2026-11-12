<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Treasury\Application\Services\TreasuryTaskInboxNotifier;
use Modules\Treasury\Domain\Events\TreasuryCovenantBreachedEvent;
use Modules\Treasury\Domain\Events\TreasuryHedgeMaturedEvent;
use Modules\Treasury\Domain\Events\TreasuryRevaluationPostedEvent;
use Modules\Treasury\Domain\Models\CreditFacility;
use Modules\Treasury\Domain\Models\ForwardContract;

/**
 * Konsumen event domain Treasury resmi (D48-11f):
 * Menangani treasury.revaluation.posted.v1, treasury.covenant.breached.v1,
 * dan treasury.hedge.matured.v1 dengan mencatat telemetri kepatuhan dan
 * mengirim penugasan tindak lanjut ke TaskInboxService.
 */
final class TreasuryDomainEventSubscriber
{
    public function __construct(
        private readonly ?TreasuryTaskInboxNotifier $taskNotifier = null
    ) {}

    public function handleRevaluationPosted(TreasuryRevaluationPostedEvent $event): void
    {
        Log::info('[TreasuryEvent] Revaluasi kurs PSAK 10 berhasil diposting', [
            'event' => TreasuryRevaluationPostedEvent::EVENT_NAME,
            'revaluation_id' => $event->revaluationId,
            'period' => $event->period,
            'currency' => $event->currency,
            'gain_loss_idr' => $event->gainLossIdr,
            'certificate_hash' => $event->certificateHash,
        ]);
    }

    public function handleCovenantBreached(TreasuryCovenantBreachedEvent $event): void
    {
        Log::warning('[TreasuryEvent] Pelanggaran atau peringatan covenant DER fasilitas kredit', [
            'event' => TreasuryCovenantBreachedEvent::EVENT_NAME,
            'facility_id' => $event->facilityId,
            'facility_code' => $event->facilityCode,
            'covenant_der_permil' => $event->covenantDerPermil,
            'max_der_permil' => $event->maxDerPermil,
            'severity' => $event->severity,
        ]);

        if ($this->taskNotifier !== null) {
            $facility = CreditFacility::find($event->facilityId);
            if ($facility !== null) {
                $this->taskNotifier->notifyCovenantNearBreach($facility, $event->covenantDerPermil);
            }
        }
    }

    public function handleHedgeMatured(TreasuryHedgeMaturedEvent $event): void
    {
        Log::info('[TreasuryEvent] Kontrak lindung nilai forward telah mencapai jatuh tempo', [
            'event' => TreasuryHedgeMaturedEvent::EVENT_NAME,
            'contract_id' => $event->contractId,
            'contract_number' => $event->contractNumber,
            'currency' => $event->currency,
            'notional_foreign_amount' => $event->notionalForeignAmount,
            'maturity_date' => $event->maturityDate,
        ]);

        if ($this->taskNotifier !== null) {
            $contract = ForwardContract::find($event->contractId);
            if ($contract !== null) {
                $this->taskNotifier->notifyUpcomingForwardMaturity($contract, 0);
            }
        }
    }
}
