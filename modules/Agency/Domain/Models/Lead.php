<?php

declare(strict_types=1);

namespace Modules\Agency\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasUuids;

    protected $table = 'agy_leads';

    protected $fillable = [
        'agent_id', 'name', 'phone', 'email', 'category', 'status',
        'estimated_value_idr', 'converted_order_id', 'notes',
    ];

    protected $casts = [
        'estimated_value_idr' => 'integer',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class, 'lead_id');
    }
}
