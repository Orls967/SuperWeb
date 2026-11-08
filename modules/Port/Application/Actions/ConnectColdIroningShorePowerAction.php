<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Port\Application\Services\ColdIroningShorePowerService;
use Modules\Port\Application\Services\PortFleetLedgerService;
use Modules\Port\Domain\Models\FleetPortCall;

/**
 * Action Penyambungan Listrik Darat Ramah Lingkungan (Cold Ironing / Onshore Power).
 */
final class ConnectColdIroningShorePowerAction
{
    public function __construct(
        private readonly ColdIroningShorePowerService $shorePowerService,
        private readonly PortFleetLedgerService $ledgerService
    ) {}

    public function execute(
        int $portCallId,
        float $connectedHours,
        float $averageDemandKw,
        float $shoreTariffPerKwh = 0.165
    ): array {
        $portCall = FleetPortCall::with('voyage')->findOrFail($portCallId);

        if ($connectedHours <= 0.0 || $averageDemandKw <= 0.0) {
            throw new DomainException('Durasi penyambungan dan beban daya listrik harus lebih besar dari 0.');
        }

        $res = $this->shorePowerService->calculateShorePowerAndAvoidedEmissions(
            $connectedHours,
            $averageDemandKw,
            $shoreTariffPerKwh
        );

        // Catat tagihan listrik dermaga di double-entry fleet ledger
        $this->ledgerService->recordBalancedTransaction(
            $portCall->voyage_id,
            $portCall->voyage->vessel_id,
            PortFleetLedgerService::ACC_EXPENSE_PORT_DUES,
            PortFleetLedgerService::ACC_PAYABLE_DISBURSEMENTS,
            $res['electricity_cost_usd'],
            'COLD_IRONING_ELECTRICITY',
            $portCall->id,
            "Biaya pemanfaatan listrik darat hijau (Cold Ironing {$connectedHours} jam) di {$portCall->port_name}"
        );

        return [
            'port_call_id' => $portCall->id,
            'metrics' => $res,
            'connected_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
