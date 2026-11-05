<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class MicroInsurancePolicy extends Model
{
    protected $table = 'ins_micro_policies';

    protected $fillable = [
        'policy_number',
        'member_party_id',
        'product_type',
        'daily_premium_idr',
        'sum_insured_idr',
        'duration_days',
        'status',
        'issued_at',
        'expires_at',
    ];

    protected $casts = [
        'daily_premium_idr' => 'integer',
        'sum_insured_idr' => 'integer',
        'duration_days' => 'integer',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
