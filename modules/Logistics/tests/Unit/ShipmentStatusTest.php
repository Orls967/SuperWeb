<?php

declare(strict_types=1);

use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Exceptions\InvalidShipmentTransitionException;
use Modules\Logistics\Domain\Models\Shipment;

test('shipment status allows legal transitions', function () {
    expect(ShipmentStatus::Draft->canTransitionTo(ShipmentStatus::Booked))->toBeTrue();
    expect(ShipmentStatus::Draft->canTransitionTo(ShipmentStatus::Cancelled))->toBeTrue();

    expect(ShipmentStatus::Booked->canTransitionTo(ShipmentStatus::PickedUp))->toBeTrue();
    expect(ShipmentStatus::Booked->canTransitionTo(ShipmentStatus::Cancelled))->toBeTrue();
    expect(ShipmentStatus::Booked->canTransitionTo(ShipmentStatus::OnHold))->toBeTrue();

    expect(ShipmentStatus::PickedUp->canTransitionTo(ShipmentStatus::InTransit))->toBeTrue();
    expect(ShipmentStatus::PickedUp->canTransitionTo(ShipmentStatus::AtHub))->toBeTrue();
    expect(ShipmentStatus::PickedUp->canTransitionTo(ShipmentStatus::ReturnToSender))->toBeTrue();

    expect(ShipmentStatus::InTransit->canTransitionTo(ShipmentStatus::AtHub))->toBeTrue();
    expect(ShipmentStatus::InTransit->canTransitionTo(ShipmentStatus::OutForDelivery))->toBeTrue();

    expect(ShipmentStatus::OutForDelivery->canTransitionTo(ShipmentStatus::Delivered))->toBeTrue();
    expect(ShipmentStatus::OutForDelivery->canTransitionTo(ShipmentStatus::Exception))->toBeTrue();
    expect(ShipmentStatus::OutForDelivery->canTransitionTo(ShipmentStatus::ReturnToSender))->toBeTrue();
});

test('shipment status rejects illegal transitions', function () {
    // Cannot cancel after pickup
    expect(ShipmentStatus::PickedUp->canTransitionTo(ShipmentStatus::Cancelled))->toBeFalse();
    expect(ShipmentStatus::InTransit->canTransitionTo(ShipmentStatus::Cancelled))->toBeFalse();
    expect(ShipmentStatus::OutForDelivery->canTransitionTo(ShipmentStatus::Cancelled))->toBeFalse();

    // Terminal statuses cannot transition anywhere
    expect(ShipmentStatus::Delivered->canTransitionTo(ShipmentStatus::InTransit))->toBeFalse();
    expect(ShipmentStatus::Returned->canTransitionTo(ShipmentStatus::Booked))->toBeFalse();
    expect(ShipmentStatus::Lost->canTransitionTo(ShipmentStatus::Delivered))->toBeFalse();
    expect(ShipmentStatus::Cancelled->canTransitionTo(ShipmentStatus::Booked))->toBeFalse();
});

test('shipment model transition throws exception on illegal transition', function () {
    $shipment = new Shipment([
        'status' => ShipmentStatus::Delivered,
    ]);

    expect(fn () => $shipment->transitionTo(ShipmentStatus::InTransit))
        ->toThrow(InvalidShipmentTransitionException::class);
});
