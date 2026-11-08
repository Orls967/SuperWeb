<?php

declare(strict_types=1);

namespace Modules\Port\Application\Sagas;

use Carbon\CarbonImmutable;
use DomainException;
use Exception;
use Illuminate\Support\Facades\DB;
use Modules\Port\Application\Services\CommercialCharterService;
use Modules\Port\Application\Services\MarineServicesDisbursementService;
use Modules\Port\Application\Services\PortFleetLedgerService;
use Modules\Port\Application\Services\PortFleetService;
use Modules\Port\Application\Services\VoyageExecutionService;
use Modules\Port\Domain\Models\FleetCharterParty;
use Modules\Port\Domain\Models\FleetVessel;
use Modules\Port\Domain\Models\FleetVoyage;
use Modules\Port\Domain\StateMachines\CharterPartyStateMachine;
use Modules\Port\Domain\StateMachines\VoyageStateMachine;

/**
 * Saga Eksekusi Fixture & Pelayaran Kapal Niaga dengan Kompensasi Penuh.
 *
 * Mengkoordinasikan validasi kelaiklautan SOLAS/STCW, reservasi jendela dermaga (K27),
 * penguncian PDA di double-entry ledger, dan komitmen fixture sewa kapal.
 */
final class VoyageCharterExecutionSaga
{
    public function __construct(
        private readonly PortFleetService $fleetService,
        private readonly VoyageExecutionService $voyageService,
        private readonly CommercialCharterService $charterService,
        private readonly MarineServicesDisbursementService $marineService,
        private readonly PortFleetLedgerService $ledgerService
    ) {}

    /**
     * Jalankan saga eksekusi fixture sewa kapal dan pelayaran.
     *
     * @param array{
     *     vessel_id: int,
     *     fixture_number: string,
     *     charterer_name: string,
     *     charter_type: string,
     *     freight_or_hire_rate: float,
     *     voyage_code: string,
     *     voyage_number: string,
     *     origin_port: string,
     *     destination_port: string,
     *     load_port: string,
     *     discharge_port: string,
     *     planned_start_at: CarbonImmutable,
     *     planned_end_at: CarbonImmutable,
     *     planned_distance_nm: float,
     *     estimated_pda_usd?: float
     * } $payload
     * @return array{
     *     success: bool,
     *     charter_party_id: int|null,
     *     voyage_id: int|null,
     *     berth_booking_id: int|null,
     *     ledger_status: string,
     *     compensation_applied: bool,
     *     error_message: string|null
     * }
     */
    public function execute(array $payload): array
    {
        $vesselId = $payload['vessel_id'];
        $charterParty = null;
        $voyage = null;
        $portCall = null;
        $berthBookingId = null;
        $ledgerEntries = null;

        try {
            return DB::transaction(function () use (
                $payload,
                $vesselId,
                &$charterParty,
                &$voyage,
                &$portCall,
                &$berthBookingId,
                &$ledgerEntries
            ) {
                // Langkah 1: Validasi Kelaiklautan (SOLAS, MARPOL, STCW safe manning)
                $audit = $this->fleetService->auditSeaworthiness($vesselId, $payload['planned_start_at']);
                if (! $audit['is_seaworthy']) {
                    throw new DomainException('Kapal tidak memenuhi kriteria kelaiklautan: '.implode('; ', $audit['reasons']));
                }

                // Langkah 2: Buat Fixture Charter Party
                $charterParty = $this->charterService->createCharterParty([
                    'fixture_number' => $payload['fixture_number'],
                    'vessel_id' => $vesselId,
                    'charterer_name' => $payload['charterer_name'],
                    'charter_type' => $payload['charter_type'],
                    'laycan_commencement' => $payload['planned_start_at'],
                    'laycan_cancellation' => $payload['planned_start_at']->addDays(5),
                    'freight_or_hire_rate' => $payload['freight_or_hire_rate'],
                ]);

                // Langkah 3: Rencanakan Pelayaran (Voyage Planning)
                $voyage = $this->voyageService->planVoyage([
                    'voyage_code' => $payload['voyage_code'],
                    'vessel_id' => $vesselId,
                    'charter_party_id' => $charterParty->id,
                    'voyage_number' => $payload['voyage_number'],
                    'origin_port' => $payload['origin_port'],
                    'destination_port' => $payload['destination_port'],
                    'load_port' => $payload['load_port'],
                    'discharge_port' => $payload['discharge_port'],
                    'planned_start_at' => $payload['planned_start_at'],
                    'planned_end_at' => $payload['planned_end_at'],
                    'planned_distance_nm' => $payload['planned_distance_nm'],
                ]);

                // Langkah 4: Buat Port Call & Cadangkan Jendela Dermaga via VesselCallWindowProvider (G7-K27)
                $portCall = $this->marineService->schedulePortCall(
                    (int) $voyage->id,
                    $payload['load_port'],
                    $payload['load_port'],
                    'LOADING',
                    $payload['planned_start_at']
                );

                $vessel = FleetVessel::findOrFail($vesselId);
                $loaM = ((float) $vessel->length_overall_cm) / 100.0;
                $draftM = ((float) $vessel->design_draft_cm) / 100.0;

                $window = $this->marineService->findAvailableBerthWindow(
                    $payload['load_port'],
                    $payload['planned_start_at'],
                    $payload['planned_start_at']->addDays(2),
                    $loaM,
                    $draftM
                );

                if (! $window || ! $window['is_available']) {
                    throw new DomainException('Tidak ada jendela dermaga yang memenuhi spesifikasi kapal di pelabuhan muat.');
                }

                $booking = $this->marineService->reserveCallWindow(
                    (int) $portCall->id,
                    $window['berth_code'],
                    CarbonImmutable::parse($window['window_start']),
                    CarbonImmutable::parse($window['window_end']),
                    $window['allocated_length_m'],
                    $window['max_draft_m']
                );
                $berthBookingId = $booking['booking_id'];

                // Langkah 5: Kunci Proforma Disbursement Account (PDA) di double-entry ledger
                $pdaAmount = $payload['estimated_pda_usd'] ?? 18500.0;
                $ledgerEntries = $this->ledgerService->recordBalancedTransaction(
                    (int) $voyage->id,
                    $vesselId,
                    PortFleetLedgerService::ACC_EXPENSE_PORT_DUES,
                    PortFleetLedgerService::ACC_PAYABLE_DISBURSEMENTS,
                    $pdaAmount,
                    'PORT_DISBURSEMENT_PDA',
                    (int) $portCall->id,
                    "Kunci alokasi Proforma Disbursement Account untuk Port Call {$portCall->port_code}"
                );

                // Langkah 6: Komitmen Fixture
                $this->charterService->transitionStatus(
                    (int) $charterParty->id,
                    CharterPartyStateMachine::STATUS_FIXTURE_AGREED
                );

                return [
                    'success' => true,
                    'charter_party_id' => (int) $charterParty->id,
                    'voyage_id' => (int) $voyage->id,
                    'berth_booking_id' => $berthBookingId,
                    'ledger_status' => 'COMMITTED',
                    'compensation_applied' => false,
                    'error_message' => null,
                ];
            });
        } catch (Exception $e) {
            // Jalankan Kompensasi Saga
            $this->compensate($berthBookingId, $charterParty, $voyage, $ledgerEntries, $e->getMessage());

            return [
                'success' => false,
                'charter_party_id' => $charterParty ? (int) $charterParty->id : null,
                'voyage_id' => $voyage ? (int) $voyage->id : null,
                'berth_booking_id' => $berthBookingId,
                'ledger_status' => 'ROLLED_BACK',
                'compensation_applied' => true,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Langkah-langkah kompensasi saat kegagalan di tengah saga.
     */
    private function compensate(
        ?int $berthBookingId,
        ?FleetCharterParty $charterParty,
        ?FleetVoyage $voyage,
        ?array $ledgerEntries,
        string $reason
    ): void {
        // 1. Batalkan booking dermaga jika sudah sempat terpesan
        if ($berthBookingId !== null) {
            $this->marineService->releaseCallWindow($berthBookingId, "Kompensasi Saga: {$reason}");
        }

        // 2. Batalkan voyage
        if ($voyage !== null && $voyage->status !== VoyageStateMachine::STATUS_CANCELLED) {
            $voyage->update(['status' => VoyageStateMachine::STATUS_CANCELLED]);
        }

        // 3. Batalkan fixture charter party
        if ($charterParty !== null && $charterParty->status !== CharterPartyStateMachine::STATUS_DISPUTED) {
            $charterParty->update(['status' => CharterPartyStateMachine::STATUS_DISPUTED]);
        }

        // 4. Balikkan entri buku besar jika sempat tercipta
        if ($ledgerEntries !== null && isset($ledgerEntries['debit_entry'])) {
            $amt = (float) $ledgerEntries['debit_entry']->amount;
            $this->ledgerService->recordBalancedTransaction(
                $voyage?->id,
                $charterParty?->vessel_id,
                PortFleetLedgerService::ACC_PAYABLE_DISBURSEMENTS,
                PortFleetLedgerService::ACC_EXPENSE_PORT_DUES,
                $amt,
                'SAGA_COMPENSATION_REVERSAL',
                (int) $ledgerEntries['debit_entry']->id,
                "Pembalikan otomatis alokasi PDA akibat kegagalan saga: {$reason}"
            );
        }
    }
}
