<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarinePayout extends Model
{
    protected $table = 'mar_payouts';

    protected $fillable = [
        'cycle_id',
        'farmer_id',
        'gross_amount',
        'advance_deduction',
        'feed_loan_deduction',
        'net_payout',
        'settled_at',
        'idempotency_key',
    ];

    protected $casts = [
        'cycle_id' => 'integer',
        'farmer_id' => 'integer',
        'gross_amount' => 'integer',
        'advance_deduction' => 'integer',
        'feed_loan_deduction' => 'integer',
        'net_payout' => 'integer',
        'settled_at' => 'datetime',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MarineCycle::class, 'cycle_id');
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_id');
    }
}
