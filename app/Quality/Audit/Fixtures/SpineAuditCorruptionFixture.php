<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\CoreEventDelivery;
use Modules\Core\Domain\StateMachines\EventDeliveryStateMachine;

final class SpineAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'spine:audit';
    }

    public function corrupt(): string
    {
        $corruptMessageId = (string) Str::uuid();

        CoreEventDelivery::create([
            'message_id' => $corruptMessageId,
            'consumer_group' => 'corrupt_consumer_fixture',
            'topic' => 'store.order',
            'status' => EventDeliveryStateMachine::STATUS_DELIVERED,
            'attempts' => 1,
        ]);

        return 'Delivery delivered without inbox record';
    }
}
