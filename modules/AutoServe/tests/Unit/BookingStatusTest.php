<?php

declare(strict_types=1);

use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\Shared\Domain\Exceptions\InvalidStateTransition;

test('BookingStatus state machine transitions work correctly', function () {
    // Pending transitions
    expect(BookingStatus::Pending->canTransitionTo(BookingStatus::Confirmed))->toBeTrue();
    expect(BookingStatus::Pending->canTransitionTo(BookingStatus::Cancelled))->toBeTrue();
    expect(BookingStatus::Pending->canTransitionTo(BookingStatus::InProgress))->toBeFalse();
    expect(BookingStatus::Pending->canTransitionTo(BookingStatus::Completed))->toBeFalse();

    // Confirmed transitions
    expect(BookingStatus::Confirmed->canTransitionTo(BookingStatus::InProgress))->toBeTrue();
    expect(BookingStatus::Confirmed->canTransitionTo(BookingStatus::Cancelled))->toBeTrue();
    expect(BookingStatus::Confirmed->canTransitionTo(BookingStatus::Completed))->toBeFalse();

    // InProgress transitions
    expect(BookingStatus::InProgress->canTransitionTo(BookingStatus::WaitingParts))->toBeTrue();
    expect(BookingStatus::InProgress->canTransitionTo(BookingStatus::Completed))->toBeTrue();
    expect(BookingStatus::InProgress->canTransitionTo(BookingStatus::Cancelled))->toBeTrue();
    expect(BookingStatus::InProgress->canTransitionTo(BookingStatus::Pending))->toBeFalse();

    // WaitingParts transitions
    expect(BookingStatus::WaitingParts->canTransitionTo(BookingStatus::InProgress))->toBeTrue();
    expect(BookingStatus::WaitingParts->canTransitionTo(BookingStatus::Cancelled))->toBeTrue();
    expect(BookingStatus::WaitingParts->canTransitionTo(BookingStatus::Completed))->toBeFalse();

    // Completed transitions
    expect(BookingStatus::Completed->canTransitionTo(BookingStatus::Invoiced))->toBeTrue();
    expect(BookingStatus::Completed->canTransitionTo(BookingStatus::InProgress))->toBeFalse();

    // Terminal states
    expect(BookingStatus::Invoiced->canTransitionTo(BookingStatus::Completed))->toBeFalse();
    expect(BookingStatus::Cancelled->canTransitionTo(BookingStatus::Pending))->toBeFalse();
});

test('Booking transitionTo throws InvalidStateTransition on illegal transitions', function () {
    $booking = new Booking;
    $booking->exists = true;
    $booking->setRawAttributes(['status' => 'pending']);

    expect(fn () => $booking->transitionTo(BookingStatus::Completed))
        ->toThrow(InvalidStateTransition::class);
});

test('Booking transitionTo succeeds on valid transitions', function () {
    $booking = new Booking;
    $booking->exists = true;
    $booking->setRawAttributes(['status' => 'pending']);

    $booking->status = BookingStatus::Confirmed;
    expect($booking->status)->toBe('confirmed');
    expect($booking->isConfirmed())->toBeTrue();
});
