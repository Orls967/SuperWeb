<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubOperator extends LogisticsEntity
{
    protected $table = 'lgx_hub_operators';

    protected $fillable = [
        'user_id',
        'hub_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'hub_id');
    }
}
