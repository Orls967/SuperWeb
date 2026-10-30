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
        'rwa_offering_id',
        'user_id',
        'tokens_requested',
        'tokens_allocated',
        'amount_held_idr',
        'amount_captured_idr',
        'amount_refunded_idr',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'rwa_offering_id' => 'integer',
        'user_id' => 'integer',
        'tokens_requested' => 'integer',
        'tokens_allocated' => 'integer',
        'amount_held_idr' => 'integer',
        'amount_captured_idr' => 'integer',
        'amount_refunded_idr' => 'integer',
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(RwaOffering::class, 'rwa_offering_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
