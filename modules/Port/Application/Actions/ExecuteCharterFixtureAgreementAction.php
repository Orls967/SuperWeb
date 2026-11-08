<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Port\Application\Services\CommercialCharterService;
use Modules\Port\Domain\Models\FleetCharterParty;
use Modules\Port\Domain\StateMachines\CharterPartyStateMachine;

/**
 * Action Eksekusi & Kesepakatan Fixture Perjanjian Sewa Kapal.
 */
final class ExecuteCharterFixtureAgreementAction
{
    public function __construct(
        private readonly CommercialCharterService $charterService
    ) {}

    public function execute(
        int $vesselId,
        string $fixtureNumber,
        string $chartererName,
        string $charterType,
        string $charterForm,
        CarbonImmutable $laycanStart,
        CarbonImmutable $laycanEnd,
        float $rateUsd,
        float $allowedLaytimeHours = 72.0,
        string $laytimeTerms = 'WWD_SHINC',
        float $demurrageRateDaily = 16000.0,
        ?float $despatchRateDaily = null,
        array $specialClauses = []
    ): FleetCharterParty {
        if ($rateUsd <= 0.0) {
            throw new DomainException('Tarif sewa kapal harus lebih besar dari 0.');
        }

        if ($laycanEnd->diffInDays($laycanStart) > 30) {
            throw new DomainException('Rentang jendela laycan tidak boleh melebihi 30 hari.');
        }

        $party = $this->charterService->createCharterParty([
            'fixture_number' => $fixtureNumber,
            'vessel_id' => $vesselId,
            'charterer_name' => $chartererName,
            'charter_type' => $charterType,
            'charter_form' => $charterForm,
            'laycan_commencement' => $laycanStart,
            'laycan_cancellation' => $laycanEnd,
            'freight_or_hire_rate' => $rateUsd,
            'laytime_allowed_hours' => $allowedLaytimeHours,
            'laytime_terms' => $laytimeTerms,
            'demurrage_rate_daily' => $demurrageRateDaily,
            'despatch_rate_daily' => $despatchRateDaily ?? ($demurrageRateDaily * 0.50),
            'special_clauses' => array_merge($specialClauses, [
                'executed_at' => CarbonImmutable::now()->toIso8601String(),
                'arbitration_venue' => 'Singapore Chamber of Maritime Arbitration (SCMA)',
            ]),
        ]);

        return $this->charterService->transitionStatus(
            (int) $party->id,
            CharterPartyStateMachine::STATUS_FIXTURE_AGREED
        );
    }
}
