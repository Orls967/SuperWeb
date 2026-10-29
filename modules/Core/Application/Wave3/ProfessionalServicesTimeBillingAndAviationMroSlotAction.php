<?php

declare(strict_types=1);

namespace Modules\Core\Application\Wave3;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class ProfessionalServicesTimeBillingAndAviationMroSlotAction extends BaseAction
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Enforces Consulting/Legal conflict-of-interest walls, IOLTA client trust escrow segregation,
     * billable WIP realization, and Aviation MRO Life-Limited Part (LLP) airworthiness dispatch gates (Fase 176-179).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params): array {
            $engagementCode = (string) ($params['engagement_code'] ?? 'W3-PRO-AVI-001');

            // 1. Fase 175-176: Professional & Legal Conflict-of-Interest Wall Check
            $clientPartyId = (string) ($params['client_party_id'] ?? 'PTY-CORP-ALPHA');
            $adversePartyId = (string) ($params['adverse_party_id'] ?? 'PTY-CORP-OMEGA');
            /** @var list<string> $activeMatterParties */
            $activeMatterParties = $params['conflicted_active_parties'] ?? [];

            if (in_array($adversePartyId, $activeMatterParties, true) || $clientPartyId === $adversePartyId) {
                throw new DomainException("Legal & professional conflict-of-interest blocked for matter {$engagementCode}: adverse party '{$adversePartyId}' conflicts with active client representation");
            }

            // Segregated Client Trust Account (IOLTA) disbursement guard
            $clientTrustBalanceMinor = (int) ($params['client_trust_balance_minor'] ?? 100000000);
            $trustDisbursementMinor = (int) ($params['trust_disbursement_minor'] ?? 20000000);
            if ($trustDisbursementMinor < 0 || $trustDisbursementMinor > $clientTrustBalanceMinor) {
                throw new DomainException("Legal IOLTA trust violation: disbursement ({$trustDisbursementMinor}) exceeds segregated client trust balance ({$clientTrustBalanceMinor})");
            }

            $billableHours = (int) ($params['billable_hours'] ?? 120);
            $hourlyRateMinor = (int) ($params['hourly_rate_minor'] ?? 2500000);
            $wipWriteDownMinor = (int) ($params['wip_write_down_minor'] ?? 10000000);
            $grossWipMinor = $billableHours * $hourlyRateMinor;
            $realizedConsultingFeeMinor = max(0, $grossWipMinor - $wipWriteDownMinor);

            // 2. Fase 177-179: Aviation MRO Airworthiness, LLP Cycle Gate & Cargo/Airline Revenue
            $aircraftTailNumber = (string) ($params['aircraft_tail_number'] ?? 'PK-SUP-801');
            $accumulatedFlightCycles = (int) ($params['accumulated_flight_cycles'] ?? 14200);
            $llpMaxCycleLimit = (int) ($params['llp_max_cycle_limit'] ?? 15000);
            $airworthinessDirectiveState = (string) ($params['airworthiness_directive_state'] ?? 'COMPLIANT_SIGNED');
            $dispatchAction = (string) ($params['flight_dispatch_action'] ?? 'DISPATCH_FLIGHT');

            $isAirworthy = ($accumulatedFlightCycles < $llpMaxCycleLimit) && ($airworthinessDirectiveState === 'COMPLIANT_SIGNED');
            $mroStatus = $isAirworthy ? 'AIRWORTHY_RELEASED_CRS' : 'AOG_GROUNDED_MRO_REQUIRED';

            if (! $isAirworthy && $dispatchAction === 'DISPATCH_FLIGHT') {
                throw new DomainException("Aviation safety gate grounded aircraft {$aircraftTailNumber}: cycles={$accumulatedFlightCycles}/{$llpMaxCycleLimit}, AD='{$airworthinessDirectiveState}'");
            }

            $mroSlotFeeMinor = (int) ($params['mro_slot_fee_minor'] ?? 180000000);
            $airCargoRevenueMinor = (int) ($params['air_cargo_revenue_minor'] ?? 95000000);
            $totalOperatingRevenueMinor = $realizedConsultingFeeMinor + $mroSlotFeeMinor + $airCargoRevenueMinor;

            $previousHash = (string) ($params['previous_hash'] ?? hash('sha256', 'GENESIS-WAVE3-PRO-AVIATION'));
            $crsAndMatterHash = hash('sha256', implode('|', [
                $engagementCode,
                $aircraftTailNumber,
                $mroStatus,
                (string) $totalOperatingRevenueMinor,
                (string) ($clientTrustBalanceMinor - $trustDisbursementMinor),
                $previousHash,
            ]));

            if ($totalOperatingRevenueMinor > 0 && $this->ledger !== null) {
                $builder = Posting::lines()
                    ->debit('wave3:pro_aviation_receivable:IDR', $totalOperatingRevenueMinor + $trustDisbursementMinor)
                    ->credit('wave3:pro_aviation_revenue:IDR', $totalOperatingRevenueMinor);

                if ($trustDisbursementMinor > 0) {
                    $builder = $builder->credit('wave3:legal_client_trust_escrow:IDR', $trustDisbursementMinor);
                }

                $idempotencyKey = hash('sha256', "W3:PROAVI:SETTLE:{$engagementCode}:{$totalOperatingRevenueMinor}");
                $this->ledger->post(new PostingDTO(
                    type: 'WAVE3_PRO_LEGAL_AVIATION',
                    description: "Professional services time-billing & Aviation MRO settlement {$engagementCode}",
                    idempotencyKey: $idempotencyKey,
                    entries: $builder->toEntries('IDR'),
                    referenceType: 'WAVE3_PRO_AVIATION',
                    referenceId: $engagementCode,
                ));
            }

            $result = [
                'engagement_code' => $engagementCode,
                'conflict_check_status' => 'CLEAR_NO_CONFLICT',
                'realized_consulting_fee_minor' => $realizedConsultingFeeMinor,
                'remaining_client_trust_minor' => $clientTrustBalanceMinor - $trustDisbursementMinor,
                'aircraft_tail_number' => $aircraftTailNumber,
                'remaining_llp_cycles' => max(0, $llpMaxCycleLimit - $accumulatedFlightCycles),
                'mro_airworthiness_status' => $mroStatus,
                'total_operating_revenue_minor' => $totalOperatingRevenueMinor,
                'crs_and_matter_hash' => $crsAndMatterHash,
            ];

            $this->audit(
                action: 'core.wave3.professional_and_aviation_mro_settled',
                newValues: [
                    'engagement_code' => $engagementCode,
                    'aircraft_tail_number' => $aircraftTailNumber,
                    'mro_status' => $mroStatus,
                    'total_operating_revenue_minor' => $totalOperatingRevenueMinor,
                ],
                correlationId: "w3_proavi_{$engagementCode}",
                impactType: 'professional_aviation'
            );

            $this->outbox(
                eventType: 'Wave3ProfessionalAndAviationMroSettled',
                payload: [
                    'engagement_code' => $engagementCode,
                    'aircraft_tail_number' => $aircraftTailNumber,
                    'crs_and_matter_hash' => $crsAndMatterHash,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:W3:PROAVI:{$engagementCode}")
            );

            return $result;
        });
    }
}
