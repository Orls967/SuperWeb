<?php

declare(strict_types=1);

namespace Modules\Rwa\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RwaOfferingOrder extends Model
{
    protected $table = 'rwa_offering_orders';

    protected $fillable = [
        'offering_id',
        'user_id',
        'quantity_requested',
        'quantity_allocated',
        'hold_reference_id',
        'amount_held_idr',
        'amount_captured_idr',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'offering_id' => 'integer',
        'user_id' => 'integer',
        'quantity_requested' => 'integer',
        'quantity_allocated' => 'integer',
        'amount_held_idr' => 'integer',
        'amount_captured_idr' => 'integer',
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(RwaOffering::class, 'offering_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
