<?php

declare(strict_types=1);

namespace Modules\Asset\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetLeasePayment extends Model
{
    protected $table = 'ast_lease_payments';

    protected $fillable = [
        'lease_id', 'period_no', 'due_date', 'payment_idr', 'interest_portion_idr',
        'principal_portion_idr', 'status', 'paid_at',
    ];

    protected $casts = [
        'period_no' => 'integer', 'due_date' => 'date',
        'payment_idr' => 'integer', 'interest_portion_idr' => 'integer',
        'principal_portion_idr' => 'integer', 'paid_at' => 'datetime',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(AssetLease::class, 'lease_id');
    }
}
