<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Aviation\Application\Exceptions\DangerousGoodsRejectedException;
use Modules\Aviation\Domain\Enums\CargoConsignmentStatus;
use Modules\Aviation\Domain\Models\AviationCargoConsignment;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Ledger\AviationAccounts;
use Modules\Aviation\Ledger\AviationLedgerPostingService;
use Modules\Aviation\Ledger\AviationTransactionTypes;
use Modules\Shared\Domain\Algorithms\Aviation\DangerousGoodsEligibilityChecker;

class AviationCargoService
{
    public function __construct(
        private readonly DangerousGoodsEligibilityChecker $dgChecker,
        private readonly AviationLedgerPostingService $ledgerPostingService
    ) {}

    /**
     * Penerimaan konsinyasi kargo udara dengan verifikasi regulasi DG IATA (A5) & buku besar.
     *
     * @param array $payload
     * @return AviationCargoConsignment
     * @throws DangerousGoodsRejectedException
     */
    public function processCargoIntake(array $payload): AviationCargoConsignment
    {
        $flight = AviationFlight::findOrFail($payload['flight_id']);
        $isDg = (bool) ($payload['is_dg'] ?? false);

        $dgCleared = false;
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
            $dgCleared = true;
        }

        return DB::transaction(function () use ($payload, $flight, $isDg, $dgCleared) {
            // Genesis custody checkpoint
            $custodyHash = hash('sha256', $payload['consignment_number'].'GENESIS_INTAKE'.now()->toIso8601String());

            $consignment = AviationCargoConsignment::create([
                'flight_id' => $flight->id,
                'consignment_number' => $payload['consignment_number'],
                'shipper_party_id' => $payload['shipper_party_id'] ?? null,
                'consignee_party_id' => $payload['consignee_party_id'] ?? null,
                'weight_g' => (int) $payload['weight_g'],
                'volume_mm3' => (int) ($payload['volume_mm3'] ?? 0),
                'nature_of_goods' => (string) $payload['nature_of_goods'],
                'un_number' => $payload['un_number'] ?? null,
                'dg_class' => $payload['dg_class'] ?? null,
                'is_dg' => $isDg,
                'dg_cleared' => $dgCleared,
                'required_temperature_min_c10' => $payload['required_temperature_min_c10'] ?? null,
                'required_temperature_max_c10' => $payload['required_temperature_max_c10'] ?? null,
                'uld_container_code' => $payload['uld_container_code'] ?? null,
                'status' => CargoConsignmentStatus::ACCEPTED->value,
                'custody_hash_chain' => $custodyHash,
            ]);

            $consignment->transitionTo(CargoConsignmentStatus::ACCEPTED, 'CARGO_PHYSICALLY_RECEIVED');

            // Hitung tarif kargo: Rp 25.000 / kg
            $kg = (int) ceil($payload['weight_g'] / 1000);
            $revenueMinor = $kg * 2500000;

            $postingRef = 'POST-CRG-'.$consignment->consignment_number;
            $this->ledgerPostingService->post(
                journalReference: $postingRef,
                transactionType: AviationTransactionTypes::CARGO_FREIGHT_INVOICED->value,
                amountMinor: $revenueMinor,
                debitAccount: AviationAccounts::CARGO_RECEIVABLE,
                creditAccount: AviationAccounts::CARGO_REVENUE,
                description: "Pendapatan kargo udara AWB {$consignment->consignment_number} berat {$kg} kg"
            );

            return $consignment;
        });
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
