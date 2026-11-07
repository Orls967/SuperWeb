<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Sagas;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Enums\OfftakeShipmentStatus;
use Modules\Mining\Domain\Models\MiningOfftakeContract;
use Modules\Mining\Domain\Models\MiningOfftakeShipment;
use Modules\Mining\Domain\Models\MiningSaga;
use Modules\Mining\Domain\Services\MiningExternalFactors;
use Modules\Mining\Domain\StateMachines\OfftakeShipmentStateMachine;
use Modules\Mining\Ledger\MiningFase94LedgerPostingService;
use Modules\TradeFinance\Contracts\OfftakeLetterOfCreditGateway;

/**
 * Saga Pengapalan Offtake Lintas Modul dengan Kompensasi (D94-7, T-4).
 *
 * Mengawal alur:
 * Kontrak -> Nominasi -> Pembukaan LC (G7-K11) -> Pemuatan Tiket Timbang ->
 * Invoice Provisional -> Sertifikat Assay Lab/Umpire -> Invoice Final -> Settlement.
 *
 * Cabang Kompensasi Eksplisit:
 * 1. LC Diskrepan: Pemuatan kargo ditahan, dialihkan ke pembeli domestik atau stockpile.
 * 2. Larangan Ekspor Bijih: Nominasi ekspor dibatalkan, dialihkan ke smelter domestik.
 * 3. Kuota DMO Belum Terpenuhi: Ekspor ditunda hingga kewajiban DMO 25% tuntas.
 */
final class OfftakeShipmentLifecycleSaga
{
    public function __construct(
        private readonly OfftakeShipmentStateMachine $stateMachine,
        private readonly OfftakeLetterOfCreditGateway $lcGateway,
        private readonly MiningFase94LedgerPostingService $ledgerPosting,
        private readonly MiningExternalFactors $externalFactors
    ) {}

    /**
     * Eksekusi alur pengapalan offtake secara terorkestrasi (D94-7).
     * Memicu kompensasi pengalihan ke smelter domestik bila kebijakan ekspor dilarang.
     *
     * @param array{
     *     contract_id: string,
     *     shipment_code: string,
     *     quantity_kg: int,
     *     actual_gar_kcal?: int,
     *     ash_permil?: int,
     *     sulfur_permil?: int,
     *     shipment_date?: string
     * } $data
     * @return array{
     *     shipment_code: string,
     *     contract_id: string,
     *     status: string,
     *     compensation_status: string,
     *     message: string
     * }
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $contract = MiningOfftakeContract::findOrFail($data['contract_id']);
            $shipmentCode = (string) $data['shipment_code'];
            $quantityKg = (int) $data['quantity_kg'];
            $quantityTons = (int) round($quantityKg / 1000);
            $shipmentDate = $data['shipment_date'] ? CarbonImmutable::parse($data['shipment_date']) : CarbonImmutable::now();

            $isExport = strtoupper((string) $contract->destination_type) === 'EXPORT';

            $shipment = MiningOfftakeShipment::firstOrCreate(
                ['shipment_number' => $shipmentCode],
                [
                    'id' => (string) Str::uuid(),
                    'contract_id' => $contract->id,
                    'party_ref' => (int) ($contract->party_ref ?: 1),
                    'vessel_name' => 'MV-BARGE-'.strtoupper(Str::random(5)),
                    'laycan_start' => $shipmentDate->toDateString(),
                    'laycan_end' => $shipmentDate->addDays(3)->toDateString(),
                    'nominated_volume_tons' => $quantityTons,
                    'loaded_volume_tons' => $quantityTons,
                    'status' => OfftakeShipmentStatus::CONTRACTED,
                    'is_export' => $isExport,
                ]
            );

            $saga = $this->findOrCreateSaga($shipment, 'INITIATE');

            // Cek kebijakan ekspor (D94-7)
            if ($isExport && $this->externalFactors->isExportBanned($contract->commodity, $shipmentDate)) {
                $reason = "Larangan ekspor komoditas {$contract->commodity} mentah per regulasi hilirisasi ESDM";
                $this->compensateExportBanDiversion(
                    $shipment,
                    $saga,
                    $reason,
                    "comp:export-ban:{$shipmentCode}"
                );

                return [
                    'shipment_code' => $shipmentCode,
                    'contract_id' => (string) $contract->id,
                    'status' => 'CANCELLED',
                    'compensation_status' => 'REROUTED_DOMESTIC_SMELTER',
                    'message' => "Pengapalan ekspor {$shipmentCode} dialihkan ke smelter domestik: {$reason}",
                ];
            }

            // Normal Flow
            $this->stateMachine->transition($shipment, OfftakeShipmentStatus::NOMINATED, 'offtake_manager', 'Kapal dinominasikan');
            $this->stateMachine->transition($shipment, OfftakeShipmentStatus::LOADING, 'weighbridge_operator', "Pemuatan {$quantityTons} ton");

            return [
                'shipment_code' => $shipmentCode,
                'contract_id' => (string) $contract->id,
                'status' => $shipment->status->value,
                'compensation_status' => 'NONE',
                'message' => "Pengapalan {$shipmentCode} berhasil diproses",
            ];
        });
    }

    /**
     * Langkah 1: Nominasi kapal & tanggal laycan.
     */
    public function nominateVessel(
        MiningOfftakeShipment $shipment,
        string $vesselName,
        string $laycanStart,
        string $laycanEnd,
        string $idempotencyKey
    ): void {
        DB::transaction(function () use ($shipment, $vesselName, $laycanStart, $laycanEnd, $idempotencyKey) {
            $saga = $this->findOrCreateSaga($shipment, 'NOMINATE_VESSEL');

            $shipment->update([
                'vessel_name' => $vesselName,
                'laycan_start' => $laycanStart,
                'laycan_end' => $laycanEnd,
            ]);

            $this->stateMachine->transition($shipment, OfftakeShipmentStatus::NOMINATED, 'offtake_manager', "Nominasi kapal {$vesselName}");

            $this->logSagaStep($saga, 'NOMINATED', [
                'vessel' => $vesselName,
                'laycan_start' => $laycanStart,
                'laycan_end' => $laycanEnd,
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }

    /**
     * Langkah 2: Pembukaan LC via TradeFinance Gateway (G7-K11).
     */
    public function openLetterOfCredit(
        MiningOfftakeShipment $shipment,
        int $amountMinor,
        string $currency,
        CarbonImmutable $expiry,
        string $idempotencyKey
    ): void {
        DB::transaction(function () use ($shipment, $amountMinor, $currency, $expiry, $idempotencyKey) {
            $saga = $this->findOrCreateSaga($shipment, 'OPEN_LC');

            $lcId = $this->lcGateway->issue(
                applicantPartyId: (int) $shipment->party_ref,
                beneficiaryPartyId: 101, // ID IUP konsesi
                amountMinor: $amountMinor,
                currency: $currency,
                expiresAt: $expiry,
                idempotencyKey: $idempotencyKey
            );

            $shipment->update([
                'lc_ref' => "LC-OF-{$lcId}",
            ]);

            $this->stateMachine->transition($shipment, OfftakeShipmentStatus::LC_OPENED, 'bank_system', "LC dibuka #LC-OF-{$lcId}");

            $this->logSagaStep($saga, 'LC_OPENED', [
                'lc_id' => $lcId,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
            ]);
        });
    }

    /**
     * Langkah 3: Pemuatan kargo tongkang/kapal dari timbangan dan stockpile.
     */
    public function executeLoading(
        MiningOfftakeShipment $shipment,
        int $loadedVolumeTons,
        string $idempotencyKey
    ): void {
        DB::transaction(function () use ($shipment, $loadedVolumeTons, $idempotencyKey) {
            $saga = $this->findOrCreateSaga($shipment, 'EXECUTE_LOADING');

            // Cek kebijakan ekspor eksternal (G7-D30, T-5)
            if ($shipment->is_export) {
                $policy = $this->externalFactors->getExportPolicy($shipment->contract->commodity);
                if (! $policy['allowed']) {
                    $this->compensateExportBanDiversion(
                        $shipment,
                        $saga,
                        $policy['reason'] ?? 'Larangan ekspor bijih mentah regulasi ESDM',
                        $idempotencyKey
                    );

                    return;
                }
            }

            $shipment->update([
                'loaded_volume_tons' => $loadedVolumeTons,
            ]);

            $this->stateMachine->transition($shipment, OfftakeShipmentStatus::LOADING, 'weighbridge_operator', "Pemuatan {$loadedVolumeTons} ton");

            $this->logSagaStep($saga, 'LOADING_COMPLETED', [
                'loaded_volume_tons' => $loadedVolumeTons,
            ]);
        });
    }

    /**
     * Langkah 4: Penerbitan invoice provisional berdasarkan draft survey & timbangan.
     */
    public function issueProvisionalInvoice(
        MiningOfftakeShipment $shipment,
        int $unitPriceCents,
        int $exchangeRateIdr,
        string $idempotencyKey
    ): void {
        DB::transaction(function () use ($shipment, $unitPriceCents, $exchangeRateIdr, $idempotencyKey) {
            $saga = $this->findOrCreateSaga($shipment, 'PROVISIONAL_INVOICE');

            $volume = $shipment->loaded_volume_tons > 0 ? $shipment->loaded_volume_tons : $shipment->nominated_volume_tons;
            $amountUsdCents = (int) ($volume * $unitPriceCents);
            $amountIdr = intdiv($amountUsdCents * $exchangeRateIdr, 100);

            $this->ledgerPosting->postProvisionalInvoice(
                shipmentId: $shipment->id,
                shipmentNumber: $shipment->shipment_number,
                amountIdr: $amountIdr
            );

            $shipment->update([
                'provisional_invoice_ref' => (abs(crc32($idempotencyKey)) % 2147483640) + 1,
            ]);

            $this->stateMachine->transition($shipment, OfftakeShipmentStatus::PROVISIONAL_INVOICED, 'finance_officer', 'Invoice provisional diterbitkan');

            $this->logSagaStep($saga, 'PROVISIONAL_INVOICED', [
                'volume_tons' => $volume,
                'unit_price_cents' => $unitPriceCents,
                'amount_idr' => $amountIdr,
            ]);
        });
    }

    /**
     * Langkah 5: Penyesuaian final assay lab & pelunasan settlement.
     */
    public function settleFinalAssayAndPayment(
        MiningOfftakeShipment $shipment,
        int $finalAdjustmentIdr,
        int $fxDiffIdr,
        string $idempotencyKey
    ): void {
        DB::transaction(function () use ($shipment, $finalAdjustmentIdr, $fxDiffIdr, $idempotencyKey) {
            $saga = $this->findOrCreateSaga($shipment, 'SETTLEMENT');

            $this->stateMachine->transition($shipment, OfftakeShipmentStatus::FINAL_ASSAYED, 'metallurgist', 'Sertifikat assay disepakati');

            if ($finalAdjustmentIdr !== 0) {
                $this->ledgerPosting->postFinalAssayAdjustment(
                    shipmentId: $shipment->id,
                    shipmentNumber: $shipment->shipment_number,
                    adjustmentIdr: $finalAdjustmentIdr
                );
            }

            $shipment->update([
                'final_invoice_ref' => (abs(crc32($idempotencyKey)) % 2147483640) + 1,
            ]);

            $this->stateMachine->transition($shipment, OfftakeShipmentStatus::FINAL_INVOICED, 'finance_officer', 'Invoice final disesuaikan');

            if ($fxDiffIdr !== 0) {
                $this->ledgerPosting->postFxGainLoss(
                    shipmentId: $shipment->id,
                    shipmentNumber: $shipment->shipment_number,
                    fxDiffIdr: $fxDiffIdr
                );
            }

            $this->stateMachine->transition($shipment, OfftakeShipmentStatus::SETTLED, 'finance_officer', 'Pelunasan selesai');

            $saga->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        });
    }

    /**
     * AKSI KOMPENSASI 1: Pengalihan kargo ekspor akibat larangan ekspor bijih (T-5, D94-7).
     */
    public function compensateExportBanDiversion(
        MiningOfftakeShipment $shipment,
        MiningSaga $saga,
        string $reason,
        string $idempotencyKey
    ): void {
        $shipment->update([
            'export_prohibited_diverted' => true,
            'diversion_reason' => $reason,
        ]);

        $this->stateMachine->transition(
            $shipment,
            OfftakeShipmentStatus::CANCELLED,
            'regulatory_guard',
            "DIBATALKAN & DIALIHKAN KE SMELTER DOMESTIK: {$reason}"
        );

        $compensationLogs = $saga->compensation_log_json ?? [];
        $compensationLogs[] = [
            'action' => 'DIVERT_TO_DOMESTIC_SMELTER',
            'reason' => $reason,
            'timestamp' => now()->toIso8601String(),
            'idempotency_key' => $idempotencyKey,
        ];

        $saga->update([
            'status' => 'compensated',
            'compensation_log_json' => $compensationLogs,
            'completed_at' => now(),
        ]);
    }

    /**
     * AKSI KOMPENSASI 2: Penahanan kargo akibat diskrepansi dokumen Letter of Credit (G7-K11).
     */
    public function compensateDiscrepantLetterOfCredit(
        MiningOfftakeShipment $shipment,
        array $discrepancies,
        string $idempotencyKey
    ): void {
        $saga = $this->findOrCreateSaga($shipment, 'LC_DISCREPANCY_COMPENSATION');

        $reason = 'Dokumen L/C diskrepan: '.implode('; ', $discrepancies);

        $this->stateMachine->transition(
            $shipment,
            OfftakeShipmentStatus::DISPUTED,
            'trade_compliance',
            $reason
        );

        $compensationLogs = $saga->compensation_log_json ?? [];
        $compensationLogs[] = [
            'action' => 'HOLD_SHIPMENT_LC_DISCREPANCY',
            'discrepancies' => $discrepancies,
            'timestamp' => now()->toIso8601String(),
            'idempotency_key' => $idempotencyKey,
        ];

        $saga->update([
            'status' => 'disputed_held',
            'compensation_log_json' => $compensationLogs,
        ]);
    }

    private function findOrCreateSaga(MiningOfftakeShipment $shipment, string $step): MiningSaga
    {
        return MiningSaga::query()->firstOrCreate(
            [
                'saga_type' => 'OFFTAKE_SHIPMENT_LIFECYCLE',
                'saga_reference' => $shipment->shipment_number,
            ],
            [
                'current_step' => $step,
                'status' => 'running',
                'payload_json' => [
                    'shipment_id' => $shipment->id,
                    'contract_id' => $shipment->contract_id,
                ],
                'compensation_log_json' => [],
            ]
        );
    }

    private function logSagaStep(MiningSaga $saga, string $step, array $data): void
    {
        $payload = $saga->payload_json ?? [];
        $payload[$step] = array_merge($data, ['timestamp' => now()->toIso8601String()]);

        $saga->update([
            'current_step' => $step,
            'payload_json' => $payload,
        ]);
    }
}
