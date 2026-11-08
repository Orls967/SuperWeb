<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Port\Application\Services\CommercialCharterService;
use Modules\Port\Domain\Models\FleetCharterParty;
use Modules\Port\Domain\Models\FleetDemurrageInvoice;
use Modules\Port\Domain\Models\FleetLaytimeCalculation;

/**
 * Action Audit Statement of Facts & Penerbitan Tagihan Demurrage/Despatch.
 */
final class IssueDemurrageDespatchInvoiceAction
{
    public function __construct(
        private readonly CommercialCharterService $charterService
    ) {}

    public function execute(
        int $charterPartyId,
        ?int $portCallId,
        string $reference,
        CarbonImmutable $norTendered,
        CarbonImmutable $workCommenced,
        CarbonImmutable $workCompleted,
        array $interruptions = []
    ): array {
        $charter = FleetCharterParty::findOrFail($charterPartyId);

        if ($workCompleted->isBefore($workCommenced)) {
            throw new DomainException('Waktu penyelesaian operasi kargo tidak boleh lebih awal dari waktu mulai.');
        }

        // Jalankan kalkulasi laytime dan generate invoice jika demurrage
        $result = $this->charterService->calculateLaytimeAndIssueInvoice(
            $charter->id,
            $portCallId,
            $reference,
            $norTendered,
            $workCommenced,
            $workCompleted,
            $interruptions
        );

        return [
            'calculation_id' => $result['calculation']->id,
            'result_type' => $result['calculation']->result_type,
            'allowed_hours' => $result['calculation']->allowed_laytime_hours,
            'used_hours' => $result['calculation']->net_laytime_used_hours,
            'demurrage_hours' => $result['calculation']->demurrage_hours,
            'despatch_hours' => $result['calculation']->despatch_hours,
            'amount_usd' => $result['calculation']->calculated_amount_usd,
            'invoice_id' => $result['invoice']?->id,
            'invoice_number' => $result['invoice']?->invoice_number,
        ];
    }
}
