<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutboxSubscription extends Model
{
    protected $table = 'core_outbox_subscriptions';

    protected $fillable = [
        'name',
        'target_type',
        'target',
        'secret',
        'events',
        'is_active',
        'timeout_seconds',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
        'timeout_seconds' => 'integer',
    ];

    protected $hidden = ['secret'];

    public function dispatches(): HasMany
    {
        return $this->hasMany(OutboxDispatch::class, 'subscription_id');
    }
}
