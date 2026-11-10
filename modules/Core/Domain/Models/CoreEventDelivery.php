<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model CoreEventDelivery
 *
 * Mengelola status siklus hidup pengiriman event (State Machine 7 Status)
 * per pasang (message_id, consumer_group).
 *
 * @property int $id
 * @property string $message_id
 * @property string $consumer_group
 * @property string $topic
 * @property string $status
 * @property int $attempts
 * @property string|null $last_error
 * @property string|null $next_retry_at
 * @property string|null $dispatched_at
 * @property string|null $delivered_at
 */
class CoreEventDelivery extends Model
{
    protected $table = 'core_event_deliveries';

    protected $fillable = [
        'message_id',
        'consumer_group',
        'topic',
        'status',
        'attempts',
        'last_error',
        'next_retry_at',
        'dispatched_at',
        'delivered_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'next_retry_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];
}
