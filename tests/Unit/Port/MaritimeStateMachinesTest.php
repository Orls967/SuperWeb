<?php

declare(strict_types=1);

use Modules\Port\Domain\StateMachines\CharterPartyStateMachine;
use Modules\Port\Domain\StateMachines\VoyageStateMachine;

describe('VoyageStateMachine (12 status)', function (): void {
    it('allows valid progression through voyage lifecycle', function (): void {
        $sm = new VoyageStateMachine;

        expect($sm->canTransition(VoyageStateMachine::STATUS_PLANNED, VoyageStateMachine::STATUS_BALLAST_PASSAGE))->toBeTrue();

        $status = $sm->transition(VoyageStateMachine::STATUS_PLANNED, VoyageStateMachine::STATUS_BALLAST_PASSAGE);
        expect($status)->toBe(VoyageStateMachine::STATUS_BALLAST_PASSAGE);

        $status = $sm->transition($status, VoyageStateMachine::STATUS_ARRIVED_LOADPORT);
        expect($status)->toBe(VoyageStateMachine::STATUS_ARRIVED_LOADPORT);

        // Guard: Tender NOR memerlukan stempel waktu
        expect(fn () => $sm->transition($status, VoyageStateMachine::STATUS_TENDERED_NOR, []))
            ->toThrow(DomainException::class);

        $status = $sm->transition($status, VoyageStateMachine::STATUS_TENDERED_NOR, ['nor_timestamp' => '2026-05-01 08:00:00']);
        expect($status)->toBe(VoyageStateMachine::STATUS_TENDERED_NOR);

        $status = $sm->transition($status, VoyageStateMachine::STATUS_COMMENCED_LOADING);
        $status = $sm->transition($status, VoyageStateMachine::STATUS_COMPLETED_LOADING);

        // Guard: Laden passage mensyaratkan B/L
        expect(fn () => $sm->transition($status, VoyageStateMachine::STATUS_LADEN_PASSAGE, []))
            ->toThrow(DomainException::class);

        $status = $sm->transition($status, VoyageStateMachine::STATUS_LADEN_PASSAGE, ['bill_of_lading_issued' => true]);
        expect($status)->toBe(VoyageStateMachine::STATUS_LADEN_PASSAGE);

        $status = $sm->transition($status, VoyageStateMachine::STATUS_ARRIVED_DISCHPORT);
        $status = $sm->transition($status, VoyageStateMachine::STATUS_DISCHARGING);
        $status = $sm->transition($status, VoyageStateMachine::STATUS_COMPLETED_DISCHARGING);
        $status = $sm->transition($status, VoyageStateMachine::STATUS_COMPLETED);

        expect($sm->isTerminal($status))->toBeTrue();
    });

    it('rejects invalid or backwards transitions', function (): void {
        $sm = new VoyageStateMachine;

        expect($sm->canTransition(VoyageStateMachine::STATUS_COMPLETED, VoyageStateMachine::STATUS_PLANNED))->toBeFalse()
            ->and(fn () => $sm->transition(VoyageStateMachine::STATUS_COMPLETED, VoyageStateMachine::STATUS_PLANNED))
            ->toThrow(DomainException::class);
    });
});

describe('CharterPartyStateMachine (8 status)', function (): void {
    it('manages fixture negotiations, on-hire, off-hire suspension and redelivery', function (): void {
        $sm = new CharterPartyStateMachine;

        expect($sm->canTransition(CharterPartyStateMachine::STATUS_NEGOTIATING, CharterPartyStateMachine::STATUS_FIXTURE_AGREED))->toBeTrue();

        $status = $sm->transition(CharterPartyStateMachine::STATUS_NEGOTIATING, CharterPartyStateMachine::STATUS_FIXTURE_AGREED);
        expect($status)->toBe(CharterPartyStateMachine::STATUS_FIXTURE_AGREED);

        // Guard: On-Hire butuh delivery certificate
        expect(fn () => $sm->transition($status, CharterPartyStateMachine::STATUS_ON_HIRE, []))
            ->toThrow(DomainException::class);

        $status = $sm->transition($status, CharterPartyStateMachine::STATUS_ON_HIRE, ['delivery_certificate_signed' => true]);
        expect($status)->toBe(CharterPartyStateMachine::STATUS_ON_HIRE);

        // Terjadi insiden mesin -> transisi ke OFF_HIRE
        $status = $sm->transition($status, CharterPartyStateMachine::STATUS_OFF_HIRE);
        expect($status)->toBe(CharterPartyStateMachine::STATUS_OFF_HIRE);

        // Survey off-hire
        $status = $sm->transition($status, CharterPartyStateMachine::STATUS_OFF_HIRE_SURVEY);
        expect($status)->toBe(CharterPartyStateMachine::STATUS_OFF_HIRE_SURVEY);

        // Kembali on-hire setelah perbaikan
        $status = $sm->transition($status, CharterPartyStateMachine::STATUS_ON_HIRE, ['delivery_certificate_signed' => true]);
        expect($status)->toBe(CharterPartyStateMachine::STATUS_ON_HIRE);

        // Guard: Re-delivery butuh bunker survey
        expect(fn () => $sm->transition($status, CharterPartyStateMachine::STATUS_RE_DELIVERED, []))
            ->toThrow(DomainException::class);

        $status = $sm->transition($status, CharterPartyStateMachine::STATUS_RE_DELIVERED, ['bunker_survey_completed' => true]);
        expect($status)->toBe(CharterPartyStateMachine::STATUS_RE_DELIVERED);

        $status = $sm->transition($status, CharterPartyStateMachine::STATUS_SETTLED);
        expect($status)->toBe(CharterPartyStateMachine::STATUS_SETTLED);
    });
});
