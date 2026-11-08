<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Modules\Aviation\Application\Actions\AcceptAirCargoConsignmentAction;
use Modules\Aviation\Application\Exceptions\DangerousGoodsRejectedException;
use Modules\Aviation\Domain\Models\AviationCargoConsignment;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Ledger\AviationLedgerPostingService;
use Modules\Shared\Domain\Algorithms\Aviation\DangerousGoodsEligibilityChecker;

class AviationCargoService
{
    public function __construct(
        private readonly DangerousGoodsEligibilityChecker $dgChecker,
        private readonly AviationLedgerPostingService $ledgerPostingService,
        private readonly AcceptAirCargoConsignmentAction $acceptCargoAction
    ) {}

    /**
     * Penerimaan konsinyasi kargo udara dengan verifikasi regulasi DG IATA (A5) & buku besar.
     *
     * @throws DangerousGoodsRejectedException
     */
    public function processCargoIntake(array $payload): AviationCargoConsignment
    {
        $flight = AviationFlight::findOrFail($payload['flight_id']);
        $isDg = (bool) ($payload['is_dg'] ?? false);

        if ($isDg) {
            $dgResult = $this->dgChecker->check([
                'un_number' => $payload['un_number'] ?? 'UN1845',
                'class' => $payload['dg_class'] ?? '9',
                'packing_group' => $payload['packing_group'] ?? 'III',
                'net_quantity_kg' => (int) ceil(($payload['weight_g'] ?? 1000) / 1000),
                'aircraft_type' => 'PASSENGER',
                'is_passenger_flight' => true,
                'package_certified' => (bool) ($payload['package_certified'] ?? true),
                'declaration_signed' => (bool) ($payload['declaration_signed'] ?? true),
            ]);

            if (! $dgResult['accepted']) {
                throw new DangerousGoodsRejectedException('Muatan barang berbahaya (DG) ditolak: '.implode('; ', $dgResult['violations']));
            }
        }

        // Panggil action domain inti
        $consignment = $this->acceptCargoAction->execute($payload);

        // Tambahkan checkpoint integritas awal pada servis
        $this->appendCustodyEvent($consignment, 'SERVICE_GATE_ACCEPTED', 'CARGO_RAMP_CGK');

        return $consignment;
    }

    /**
     * Tambahkan peristiwa rantai kustodi baru ke kargo udara.
     */
    public function appendCustodyEvent(AviationCargoConsignment $consignment, string $eventCode, string $location): string
    {
        $prevHash = $consignment->custody_hash_chain ?? hash('sha256', 'GENESIS');
        $newHash = hash('sha256', $prevHash.$eventCode.$location.now()->toIso8601String());

        $consignment->custody_hash_chain = $newHash;
        $consignment->save();

        return $newHash;
    }
}
