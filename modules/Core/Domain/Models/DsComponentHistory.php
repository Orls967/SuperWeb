<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DsComponentHistory extends Model
{
    protected $table = 'core_ds_component_history';

    protected $fillable = [
        'component_id',
        'from_status',
        'to_status',
        'actor_id',
        'actor_role',
        'reason',
        'metadata',
        'ip_address',
        'transitioned_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'transitioned_at' => 'datetime',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(DsComponent::class, 'component_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
