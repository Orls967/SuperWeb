<?php

declare(strict_types=1);

namespace Modules\Wealth\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WmSweepConfig extends Model
{
    protected $table = 'wm_sweep_configs';

    protected $fillable = [
        'user_id',
        'profile_id',
        'threshold_cash_idr',
        'sweep_percentage_bps',
        'idle_cash_threshold_idr',
        'target_buffer_idr',
        'sweep_action',
        'is_active',
        'idempotency_key',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'profile_id' => 'integer',
        'threshold_cash_idr' => 'integer',
        'sweep_percentage_bps' => 'integer',
        'idle_cash_threshold_idr' => 'integer',
        'target_buffer_idr' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(WmProfile::class, 'profile_id');
    }
}
