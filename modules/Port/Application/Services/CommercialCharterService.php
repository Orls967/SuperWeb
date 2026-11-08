<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Port\Domain\Models\FleetBunkerAdjustment;
use Modules\Port\Domain\Models\FleetCharterParty;
use Modules\Port\Domain\Models\FleetDemurrageInvoice;
use Modules\Port\Domain\Models\FleetFreightContract;
use Modules\Port\Domain\Models\FleetLaytimeCalculation;
use Modules\Port\Domain\Models\FleetOffHireEvent;
use Modules\Port\Domain\Models\FleetVessel;
use Modules\Port\Domain\StateMachines\CharterPartyStateMachine;
use Modules\Shared\Domain\Algorithms\Port\LaytimeDemurrageCalculator;

/**
 * Service Manajemen Komersial Sewa Kapal & Perhitungan Laytime (Commercial Charter Service).
 * Mengatur kontrak charter party (Gencon/NYPE), perhitungan demurrage/despatch berdasarkan SOF,
 * insiden off-hire kapal sewa waktu, dan penyesuaian bunker on/off hire.
 */
final class CommercialCharterService
{
    public function __construct(
        private readonly CharterPartyStateMachine $stateMachine = new CharterPartyStateMachine,
        private readonly LaytimeDemurrageCalculator $laytimeCalculator = new LaytimeDemurrageCalculator
    ) {}

    /**
     * Buat fixture perjanjian sewa kapal baru (Charter Party Agreement).
     *
     * @param array{
     *     fixture_number: string,
     *     vessel_id: int,
     *     charterer_name: string,
     *     charter_type: string, // TIME_CHARTER, VOYAGE_CHARTER, BAREBOAT
     *     charter_form?: string,
     *     laycan_commencement: CarbonImmutable,
     *     laycan_cancellation: CarbonImmutable,
     *     freight_or_hire_rate: float,
     *     currency?: string,
     *     laytime_allowed_hours?: float,
     *     laytime_terms?: string,
     *     demurrage_rate_daily?: float,
     *     despatch_rate_daily?: float,
     *     special_clauses?: array
     * } $data
     */
    public function createCharterParty(array $data): FleetCharterParty
    {
        $vessel = FleetVessel::findOrFail($data['vessel_id']);

        if ($data['laycan_cancellation']->isBefore($data['laycan_commencement'])) {
            throw new DomainException('Tanggal pembatalan laycan tidak boleh lebih awal dari tanggal mulai laycan.');
        }

        return FleetCharterParty::create([
            'fixture_number' => $data['fixture_number'],
            'vessel_id' => $vessel->id,
            'charterer_name' => $data['charterer_name'],
            'charter_type' => $data['charter_type'],
            'charter_form' => $data['charter_form'] ?? 'GENCON_1994',
            'laycan_commencement' => $data['laycan_commencement']->toDateString(),
            'laycan_cancellation' => $data['laycan_cancellation']->toDateString(),
            'freight_or_hire_rate' => $data['freight_or_hire_rate'],
            'currency' => $data['currency'] ?? 'USD',
            'laytime_allowed_hours' => $data['laytime_allowed_hours'] ?? 72.0,
            'laytime_terms' => $data['laytime_terms'] ?? 'WWD_SHINC',
            'demurrage_rate_daily' => $data['demurrage_rate_daily'] ?? 15000.0,
            'despatch_rate_daily' => $data['despatch_rate_daily'] ?? (($data['demurrage_rate_daily'] ?? 15000.0) * 0.50),
            'status' => CharterPartyStateMachine::STATUS_NEGOTIATING,
            'special_clauses' => $data['special_clauses'] ?? [],
        ]);
    }

    /**
     * Transisikan status fixture charter party.
     */
    public function transitionStatus(int $charterPartyId, string $newStatus, array $context = []): FleetCharterParty
    {
        $charter = FleetCharterParty::findOrFail($charterPartyId);
        $status = $this->stateMachine->transition($charter->status, $newStatus, $context);
        $charter->update(['status' => $status]);

        return $charter->fresh();
    }

    /**
     * Terbitkan kontrak muatan (Freight Contract / COA).
     */
    public function createFreightContract(
        int $charterPartyId,
        string $contractNumber,
        string $cargoDescription,
        float $quantityMt,
        float $freightRatePerMt,
        float $tolerancePercentage = 5.0,
        float $advanceFreightPaid = 0.0
    ): FleetFreightContract {
        $totalFreight = $quantityMt * $freightRatePerMt;

        return FleetFreightContract::create([
            'charter_party_id' => $charterPartyId,
            'contract_number' => $contractNumber,
            'cargo_description' => $cargoDescription,
            'quantity_mt' => $quantityMt,
            'tolerance_percentage' => $tolerancePercentage,
            'freight_rate_per_mt' => $freightRatePerMt,
            'total_freight_estimate' => round($totalFreight, 2),
            'advance_freight_paid' => round($advanceFreightPaid, 2),
            'deadfreight_incurred' => 0.0,
            'payment_status' => $advanceFreightPaid >= $totalFreight ? 'FULLY_PAID' : ($advanceFreightPaid > 0 ? 'PARTIALLY_PAID' : 'PENDING'),
        ]);
    }

    /**
     * Hitung laytime dari Statement of Facts (SOF) dan terbitkan kalkulasi resmi demurrage/despatch.
     *
     * @param  array<int, array{start: CarbonImmutable, end: CarbonImmutable, reason: string, is_excepted: bool}>  $interruptions
     * @return array{calculation: FleetLaytimeCalculation, invoice: FleetDemurrageInvoice|null}
     */
    public function calculateLaytimeAndIssueInvoice(
        int $charterPartyId,
        ?int $portCallId,
        string $calcRef,
        CarbonImmutable $norTenderedAt,
        CarbonImmutable $workCommencedAt,
        CarbonImmutable $workCompletedAt,
        array $interruptions = []
    ): array {
        $charter = FleetCharterParty::findOrFail($charterPartyId);

        $result = $this->laytimeCalculator->calculateStatement(
            $norTenderedAt,
            $workCommencedAt,
            $workCompletedAt,
            (float) $charter->laytime_allowed_hours,
            (float) $charter->demurrage_rate_daily,
            (float) $charter->despatch_rate_daily,
            str_contains($charter->laytime_terms, 'SHEX') ? 'SHEX' : 'SHINC',
            6,
            $interruptions
        );

        $calculation = FleetLaytimeCalculation::create([
            'charter_party_id' => $charter->id,
            'port_call_id' => $portCallId,
            'calculation_reference' => $calcRef,
            'nor_tendered_at' => $norTenderedAt->toDateTimeString(),
            'laytime_commenced_at' => CarbonImmutable::parse($result['laytime_commenced_at'])->toDateTimeString(),
            'cargo_completed_at' => $workCompletedAt->toDateTimeString(),
            'allowed_laytime_hours' => $result['allowed_hours'],
            'rain_interruption_hours' => $result['excepted_hours'],
            'breakdown_interruption_hours' => 0.0,
            'holiday_excluded_hours' => 0.0,
            'net_laytime_used_hours' => $result['net_laytime_used_hours'],
            'demurrage_hours' => $result['demurrage_hours'],
            'despatch_hours' => $result['despatch_hours'],
            'daily_demurrage_rate' => $charter->demurrage_rate_daily,
            'calculated_amount_usd' => $result['settlement_amount_usd'],
            'result_type' => $result['result_type'],
            'settlement_status' => 'PROPOSED',
            'statement_of_facts' => [
                'interruptions_count' => count($interruptions),
                'balance_hours' => $result['balance_hours'],
            ],
        ]);

        $invoice = null;
        if ($result['result_type'] === 'DEMURRAGE' && $result['settlement_amount_usd'] > 0) {
            $invoice = FleetDemurrageInvoice::create([
                'laytime_calculation_id' => $calculation->id,
                'invoice_number' => 'DEM-'.strtoupper(bin2hex(random_bytes(4))),
                'bill_to_party' => $charter->charterer_name,
                'amount_usd' => $result['settlement_amount_usd'],
                'invoice_date' => CarbonImmutable::now()->toDateString(),
                'due_date' => CarbonImmutable::now()->addDays(14)->toDateString(),
                'status' => 'UNPAID',
            ]);
        }

        return [
            'calculation' => $calculation,
            'invoice' => $invoice,
        ];
    }

    /**
     * Catat insiden off-hire kapal sewa waktu (Time Charter) dan hitung pengurangan uang sewa (hire deduction).
     */
    public function recordOffHireIncident(
        int $charterPartyId,
        string $incidentCode,
        string $category,
        CarbonImmutable $startAt,
        CarbonImmutable $endAt,
        float $bunkerConsumedDuringOffHireMt = 0.0
    ): FleetOffHireEvent {
        $charter = FleetCharterParty::with('vessel')->findOrFail($charterPartyId);

        $durationSeconds = $endAt->getTimestamp() - $startAt->getTimestamp();
        $durationHours = max(0.0, $durationSeconds / 3600.0);
        $dailyRate = (float) $charter->freight_or_hire_rate;

        // Pengurangan uang sewa: (duration_hours / 24) * daily_rate
        $totalHireDeducted = ($durationHours / 24.0) * $dailyRate;

        // Nilai bunker yang dikonsumsi selama off-hire dibebankan ke pemilik kapal
        $bunkerReimbursement = $bunkerConsumedDuringOffHireMt * 650.0; // estimasi 650 USD/MT

        // Transisi status charter ke OFF_HIRE jika sedang ON_HIRE
        if ($charter->status === CharterPartyStateMachine::STATUS_ON_HIRE) {
            $charter->update(['status' => CharterPartyStateMachine::STATUS_OFF_HIRE]);
        }

        return FleetOffHireEvent::create([
            'charter_party_id' => $charter->id,
            'vessel_id' => $charter->vessel_id,
            'incident_code' => $incidentCode,
            'reason_category' => $category,
            'off_hire_start_at' => $startAt->toDateTimeString(),
            'off_hire_end_at' => $endAt->toDateTimeString(),
            'duration_hours' => round($durationHours, 2),
            'daily_hire_deduction_rate' => $dailyRate,
            'total_hire_deducted_usd' => round($totalHireDeducted, 2),
            'bunker_consumed_during_off_hire_mt' => $bunkerConsumedDuringOffHireMt,
            'bunker_reimbursement_usd' => round($bunkerReimbursement, 2),
            'status' => 'AGREED',
        ]);
    }

    /**
     * Rekonsiliasi bunker saat serah-terima sewa kapal (Bunker on Delivery vs Bunker on Redelivery).
     */
    public function recordBunkerSurveyAdjustment(
        int $charterPartyId,
        string $eventType, // DELIVERY, REDELIVERY
        float $vlsfoMt,
        float $lsmgoMt,
        float $vlsfoPrice,
        float $lsmgoPrice,
        ?string $surveyor = 'Saybolt Marine Surveyors',
        ?string $certRef = null
    ): FleetBunkerAdjustment {
        $charter = FleetCharterParty::findOrFail($charterPartyId);
        $totalValue = ($vlsfoMt * $vlsfoPrice) + ($lsmgoMt * $lsmgoPrice);

        return FleetBunkerAdjustment::create([
            'charter_party_id' => $charter->id,
            'event_type' => $eventType,
            'vlsfo_quantity_mt' => $vlsfoMt,
            'lsmgo_quantity_mt' => $lsmgoMt,
            'vlsfo_price_per_mt' => $vlsfoPrice,
            'lsmgo_price_per_mt' => $lsmgoPrice,
            'total_value_usd' => round($totalValue, 2),
            'surveyor_company' => $surveyor,
            'certificate_ref' => $certRef ?? ('BODR-'.strtoupper(bin2hex(random_bytes(3)))),
        ]);
    }
}
