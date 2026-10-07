<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class InsurancePolicy extends Model
{
    protected $table = 'ins_policies';

    protected $fillable = [
        'policy_number',
        'product_id',
        'user_id',
        'subject_ref_type',
        'subject_ref_id',
        'premium_paid_idr',
        'coverage_limit_idr',
        'starts_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'premium_paid_idr' => 'integer',
        'coverage_limit_idr' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(InsuranceProduct::class, 'product_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
