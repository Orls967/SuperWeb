<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CateringPayrollDeduction extends Model
{
    protected $table = 'ckt_payroll_deductions';

    protected $fillable = [
        'subscription_id',
        'payroll_period',
        'amount_idr',
        'idempotency_key',
    ];

    protected $casts = [
        'amount_idr' => 'integer',
    ];

    public function subscription()
    {
        return $this->belongsTo(CateringSubscription::class, 'subscription_id');
    }
}
