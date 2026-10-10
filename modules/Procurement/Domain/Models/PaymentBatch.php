<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Batch payment run pemasok (34.5) — menuntut approval sebelum eksekusi.
 */
class PaymentBatch extends Model
{
    protected $table = 'prc_payment_batches';

    protected $fillable = [
        'number', 'status', 'total_amount_idr', 'item_count', 'scheduled_date',
        'proof', 'approval_id', 'created_by_user_id',
    ];

    protected $casts = [
        'total_amount_idr' => 'integer', 'item_count' => 'integer',
        'scheduled_date' => 'date', 'approval_id' => 'string', 'created_by_user_id' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PaymentItem::class, 'batch_id');
    }
}
