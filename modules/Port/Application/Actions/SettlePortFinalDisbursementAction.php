<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Port\Application\Services\MarineServicesDisbursementService;
use Modules\Port\Application\Services\PortFleetLedgerService;
use Modules\Port\Domain\Models\FleetPortCall;
use Modules\Port\Domain\Models\FleetPortDisbursement;

/**
 * Action Audit & Penyelesaian Final Disbursement Account (FDA).
 */
final class SettlePortFinalDisbursementAction
{
    public function __construct(
        private readonly MarineServicesDisbursementService $marineService,
        private readonly PortFleetLedgerService $ledgerService
    ) {}

    public function execute(
        int $portCallId,
        string $fdaReference,
        float $actualPortDues,
        float $actualPilotage,
        float $actualTowage,
        float $actualBerthHire,
        float $actualLinesmen = 450.0,
        float $actualWasteReception = 600.0,
        float $actualAgencyFee = 2500.0,
        float $actualCustoms = 800.0
    ): FleetPortDisbursement {
        $portCall = FleetPortCall::with('voyage')->findOrFail($portCallId);

        $fda = $this->marineService->finalizeDisbursementAccount(
            $portCall->id,
            $fdaReference,
            $actualPortDues,
            $actualPilotage,
            $actualTowage,
            $actualBerthHire,
            $actualLinesmen,
            $actualWasteReception,
            $actualAgencyFee,
            $actualCustoms
        );

        // Jika terdapat variansi (kelebihan/kekurangan) vs Proforma PDA, catat entri penyesuaian di ledger
        if (abs($fda->variance_with_pda_usd) > 0.01) {
            $variance = (float) $fda->variance_with_pda_usd;
            if ($variance > 0) {
                // Biaya aktual lebih besar daripada estimasi PDA (tambah expense)
                $this->ledgerService->recordBalancedTransaction(
                    $portCall->voyage_id,
                    $portCall->voyage->vessel_id,
                    PortFleetLedgerService::ACC_EXPENSE_PORT_DUES,
                    PortFleetLedgerService::ACC_PAYABLE_DISBURSEMENTS,
                    $variance,
                    'FDA_VARIANCE_ADJUSTMENT',
                    $fda->id,
                    "Penyesuaian kekurangan alokasi PDA untuk {$fdaReference}"
                );
            } else {
                // Biaya aktual lebih hemat daripada estimasi PDA (balikkan payable)
                $this->ledgerService->recordBalancedTransaction(
                    $portCall->voyage_id,
                    $portCall->voyage->vessel_id,
                    PortFleetLedgerService::ACC_PAYABLE_DISBURSEMENTS,
                    PortFleetLedgerService::ACC_EXPENSE_PORT_DUES,
                    abs($variance),
                    'FDA_VARIANCE_SAVINGS',
                    $fda->id,
                    "Pengembalian penghematan pengeluaran pelabuhan untuk {$fdaReference}"
                );
            }
        }

        return $fda;
    }
}
