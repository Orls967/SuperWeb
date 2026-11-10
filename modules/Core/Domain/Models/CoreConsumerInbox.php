<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model CoreConsumerInbox
 *
 * Menyimpan rekaman pesan yang berhasil diklaim dan diproses oleh consumer group,
 * menjamin idempotensi tahan-restart di tingkat database.
 *
 * @property int $id
 * @property string $consumer_group
 * @property string $message_id
 * @property string|null $handler_class
 * @property string|null $response_hash
 * @property int $execution_time_ms
 * @property string $processed_at
 */
class CoreConsumerInbox extends Model
{
    protected $table = 'core_consumer_inbox';

    protected $fillable = [
        'consumer_group',
        'message_id',
        'handler_class',
        'response_hash',
        'execution_time_ms',
        'processed_at',
    ];

    protected $casts = [
        'execution_time_ms' => 'integer',
        'processed_at' => 'datetime',
    ];
}
