<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Enums\CargoConsignmentStatus;
use Modules\Aviation\Domain\Exceptions\DangerousGoodsRejectedException;
use Modules\Aviation\Domain\Exceptions\IdempotencyConflictException;
use Modules\Aviation\Domain\Models\AviationCargoConsignment;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Ledger\AviationLedgerPostingService;
use Modules\Logistics\Contracts\CustodyVerifier;
use Modules\Shared\Domain\Algorithms\Aviation\DangerousGoodsEligibilityChecker;

class AcceptAirCargoConsignmentAction
{
    public function __construct(
        private readonly DangerousGoodsEligibilityChecker $dgChecker,
        private readonly AviationLedgerPostingService $ledgerService,
        private readonly ?CustodyVerifier $custodyVerifier = null
    ) {
    }

    /**
     * @param array{
     *   flight_id: int,
     *   shipper_party_id: int|null,
     *   consignee_party_id: int|null,
     *   weight_g: int,
     *   nature_of_goods: string,
     *   un_number?: string|null,
     *   transport_mode?: string,
     *   uld_holdover_minutes?: int|null,
     *   required_temperature_min_c10?: int|null,
     *   required_temperature_max_c10?: int|null,
     *   uld_container_code?: string|null,
     *   idempotency_key: string
     * } $payload
     * @return AviationCargoConsignment
     */
    public function execute(array $payload): AviationCargoConsignment
    {
        $idempotencyKey = $payload['idempotency_key'];

        return DB::transaction(function () use ($payload, $idempotencyKey) {
            $existing = AviationCargoConsignment::where('consignment_number', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $flight = AviationFlight::with('route')->findOrFail($payload['flight_id']);
            $sectorMinutes = $flight->route ? $flight->route->scheduled_duration_minutes : 120;

            $isDg = ! empty($payload['un_number']);
            $dgCleared = false;

            if ($isDg) {
                $unNum = (string) $payload['un_number'];
                $mode = $payload['transport_mode'] ?? 'PAX';
                $holdover = $payload['uld_holdover_minutes'] ?? 300;

                $dgResult = $this->dgChecker->check(
                    unNumber: $unNum,
                    mode: $mode,
                    weightGrams: $payload['weight_g'],
                    sectorDurationMinutes: $sectorMinutes,
                    uldHoldoverMinutes: $holdover
                );

                if (! $dgResult->isEligible) {
                    throw DangerousGoodsRejectedException::withViolations($unNum, $dgResult->violations);
                }

                $dgCleared = true;
            }

            // Bangun hash chain kustodi
            $custodyHash = hash('sha256', sprintf(
                '%d|%d|%d|%s|%s',
                $flight->id,
                $payload['weight_g'],
                $payload['shipper_party_id'] ?? 0,
                $payload['un_number'] ?? 'NON-DG',
                $idempotencyKey
            ));

            $consignmentNum = sprintf('AWB-%d-%05d', $flight->id, rand(1000, 9999));

            $consignment = AviationCargoConsignment::create([
                'consignment_number' => $consignmentNum,
                'flight_id' => $flight->id,
                'shipper_party_id' => $payload['shipper_party_id'] ?? null,
                'consignee_party_id' => $payload['consignee_party_id'] ?? null,
                'weight_g' => $payload['weight_g'],
                'volume_mm3' => $payload['volume_mm3'] ?? 0,
                'nature_of_goods' => $payload['nature_of_goods'],
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
            if ($dgCleared) {
                $consignment->transitionTo(CargoConsignmentStatus::DG_CLEARED, 'DG_INSPECTION_PASSED');
            }

            // Hitung tarif kargo: Rp25.000 / kg
            $ratePerKgIdr = 2500000; // in minor IDR
            $kg = (int) ceil($payload['weight_g'] / 1000);
            $freightRevenueMinor = $kg * $ratePerKgIdr;

            // Jurnal piutang pendapatan kargo
            $ledgerKey = "avi:cargo:rev:{$consignment->id}:{$idempotencyKey}";
            $this->ledgerService->postCargoRevenue($consignment, $freightRevenueMinor, $ledgerKey);

            return $consignment;
        });
    }
}

