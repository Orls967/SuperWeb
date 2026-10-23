<?php

declare(strict_types=1);

namespace Modules\Wealth\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class WmOrder extends Model
{
    protected $table = 'wm_orders';

    protected $fillable = [
        'profile_id',
        'asset_type',
        'amount_idr',
        'status',
        'idempotency_key',
        'executed_at',
    ];

    protected $casts = [
        'amount_idr' => 'integer',
        'executed_at' => 'datetime',
    ];

    public function profile()
    {
        return $this->belongsTo(WmProfile::class, 'profile_id');
    }
}
