<?php

declare(strict_types=1);

use Modules\Shared\Application\MenuRegistry;
use Modules\Shared\Domain\Exceptions\InvalidStateTransition;
use Modules\Shared\Domain\ValueObjects\Money;

test('Money can be instantiated and formatted for IDR', function () {
    $money = Money::IDR(1500000);
    expect($money->format())->toBe('Rp 1.500.000');
    expect($money->isPositive())->toBeTrue();
    expect($money->isNegative())->toBeFalse();
    expect($money->isZero())->toBeFalse();
});

test('Money arithmetic operations work correctly without floating point errors', function () {
    $m1 = Money::IDR(50000);
    $m2 = Money::IDR(25000);

    expect($m1->add($m2)->format())->toBe('Rp 75.000');
    expect($m1->sub($m2)->format())->toBe('Rp 25.000');
    expect($m1->multiply(2)->format())->toBe('Rp 100.000');
    expect($m1->isGreaterThan($m2))->toBeTrue();
    expect($m2->isLessThan($m1))->toBeTrue();
});

test('Money rejects operations between different assets', function () {
    $idr = Money::IDR(1000);
    $btc = Money::of('BTC', '0.001');

    $idr->add($btc);
})->throws(InvalidArgumentException::class);

test('InvalidStateTransition throws with clear message', function () {
    $exception = InvalidStateTransition::fromTo('pending', 'completed', 'Booking');
    expect($exception->getMessage())->toContain("[Booking] Invalid state transition from 'pending' to 'completed'.");
});

test('MenuRegistry registers and filters items by role and sort order', function () {
    $registry = new MenuRegistry;
    $registry->addItem(
        label: 'Admin Only',
        route: 'admin.dashboard',
        roles: ['admin'],
        order: 10
    );
    $registry->addItem(
        label: 'Public',
        route: 'home',
        roles: [],
        order: 5
    );

    $customer = (object) ['role' => 'customer'];
    $admin = (object) ['role' => 'admin'];

    $customerItems = $registry->getItemsForUser($customer);
    expect($customerItems)->toHaveCount(1);
    expect($customerItems[0]->label)->toBe('Public');

    $adminItems = $registry->getItemsForUser($admin);
    expect($adminItems)->toHaveCount(2);
    expect($adminItems[0]->label)->toBe('Public');
    expect($adminItems[1]->label)->toBe('Admin Only');
});
