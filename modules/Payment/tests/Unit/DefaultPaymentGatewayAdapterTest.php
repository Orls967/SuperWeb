<?php

declare(strict_types=1);

namespace Modules\Payment\tests\Unit;

use Modules\Payment\Application\Services\DefaultPaymentGatewayAdapter;
use Tests\TestCase;

uses(TestCase::class);

it('handles authorize, capture and refund correctly', function (): void {
    $adapter = new DefaultPaymentGatewayAdapter;

    $auth = $adapter->authorize(['amount' => 1000]);
    expect($auth)->toBe(['status' => 'authorized', 'params' => ['amount' => 1000]]);

    $cap = $adapter->capture(['amount' => 1000, 'idempotencyKey' => 'key-1']);
    expect($cap)->toBe(['status' => 'captured', 'params' => ['amount' => 1000, 'idempotencyKey' => 'key-1']]);

    $ref = $adapter->refund(['amount' => 500]);
    expect($ref)->toBe(['status' => 'refunded', 'params' => ['amount' => 500]]);
});
