<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Mining\Domain\Enums\OfftakeShipmentStatus;
use Modules\Mining\Domain\Models\MiningOfftakeContract;
use Modules\Mining\Domain\Models\MiningOfftakeShipment;
use Modules\Mining\Domain\StateMachines\OfftakeShipmentStateMachine;
use Modules\TradeFinance\Contracts\OfftakeLetterOfCreditGateway;

class NominateOfftakeVesselAction
{
    public function __construct(
        private readonly OfftakeShipmentStateMachine $stateMachine,
        private readonly OfftakeLetterOfCreditGateway $lcGateway
    ) {}

    /**
     * Daftarkan nominasi kapal pengangkut offtake batubara/mineral (D94-7, G7-K11).
     *
     * @param array{
     *     contract_id: string,
     *     vessel_name: string,
     *     laycan_start: string,
     *     laycan_end: string,
     *     nominated_volume_tons: int
     * } $data
     */
    public function execute(array $data): MiningOfftakeShipment
    {
        return DB::transaction(function () use ($data) {
            $contractId = (string) $data['contract_id'];
            $vessel = trim((string) $data['vessel_name']);
            $laycanStart = CarbonImmutable::parse($data['laycan_start']);
            $laycanEnd = CarbonImmutable::parse($data['laycan_end']);
            $volumeTons = (int) $data['nominated_volume_tons'];

            if ($volumeTons <= 0) {
                throw new InvalidArgumentException('Volume nominasi kapal harus lebih besar dari nol.');
            }

            if ($laycanEnd->lessThan($laycanStart)) {
                throw new InvalidArgumentException('Tanggal akhir laycan tidak boleh lebih awal dari awal laycan.');
            }

            $contract = MiningOfftakeContract::findOrFail($contractId);

            $isExport = strtoupper((string) $contract->destination_type) === 'EXPORT';
            $shipmentNumber = 'SHP-'.strtoupper(substr($contract->commodity, 0, 3)).'-'.date('Ymd').'-'.strtoupper(Str::random(5));

            $shipment = MiningOfftakeShipment::create([
                'id' => (string) Str::uuid(),
                'contract_id' => $contract->id,
                'party_ref' => (int) ($contract->party_ref ?: 1),
                'shipment_number' => $shipmentNumber,
                'vessel_name' => $vessel,
                'laycan_start' => $laycanStart->toDateString(),
                'laycan_end' => $laycanEnd->toDateString(),
                'nominated_volume_tons' => $volumeTons,
                'loaded_volume_tons' => 0,
                'status' => OfftakeShipmentStatus::CONTRACTED,
                'is_export' => $isExport,
            ]);

            $this->stateMachine->transition(
                $shipment,
                OfftakeShipmentStatus::NOMINATED,
                'offtake_manager',
                "Nominasi kapal {$vessel} volume {$volumeTons} ton"
            );

            // Buka Letter of Credit otomatis via TradeFinance gateway (G7-K11) bila kontrak mewajibkan
            if ($contract->lc_required) {
                $amountUsdCents = $volumeTons * (int) ($contract->base_price_cents ?: $contract->base_price_usd_cents ?: 8500);
                $lcId = $this->lcGateway->issue(
                    applicantPartyId: (int) $shipment->party_ref,
                    beneficiaryPartyId: 101, // IUP Beneficiary
                    amountMinor: $amountUsdCents,
                    currency: 'USD',
                    expiresAt: $laycanEnd->addDays(30),
                    idempotencyKey: "min:offtake-lc:{$shipmentNumber}"
                );

                $shipment->update(['lc_ref' => "LC-TF-{$lcId}"]);
                $this->stateMachine->transition(
                    $shipment,
                    OfftakeShipmentStatus::LC_OPENED,
                    'trade_finance',
                    "L/C terkonfirmasi dibuka #LC-TF-{$lcId}"
                );
            }

            return $shipment;
        });
    }
}
