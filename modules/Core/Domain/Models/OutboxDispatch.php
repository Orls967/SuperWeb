<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboxDispatch extends Model
{
    protected $table = 'core_outbox_dispatches';

    protected $fillable = [
        'outbox_id',
        'subscription_id',
        'status',
        'attempt',
        'response_code',
        'response_body',
        'dispatched_at',
    ];

    protected $casts = [
        'attempt' => 'integer',
        'response_code' => 'integer',
        'dispatched_at' => 'datetime',
    ];

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(OutboxMessage::class, 'outbox_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(OutboxSubscription::class, 'subscription_id');
    }
}
