<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model CorePoisonMessage
 *
 * Mengisolasi pesan poison yang gagal validasi skema atau handler berturut-turut,
 * mencegah blocking offset pada consumer group.
 *
 * @property int $id
 * @property string $message_key
 * @property string $consumer_group
 * @property string $topic
 * @property string|null $event_name
 * @property array $payload
 * @property string $failure_reason
 * @property string|null $exception_class
 * @property string|null $stack_trace
 * @property int $attempts_count
 * @property string $quarantined_at
 */
class CorePoisonMessage extends Model
{
    protected $table = 'core_poison_messages';

    protected $fillable = [
        'message_key',
        'consumer_group',
        'topic',
        'event_name',
        'payload',
        'failure_reason',
        'exception_class',
        'stack_trace',
        'attempts_count',
        'quarantined_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts_count' => 'integer',
        'quarantined_at' => 'datetime',
    ];

    public function getMessageIdAttribute(): string
    {
        return (string) ($this->attributes['message_key'] ?? '');
    }

    public function setMessageIdAttribute(string $value): void
    {
        $this->attributes['message_key'] = $value;
    }
}
