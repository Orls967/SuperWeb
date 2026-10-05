<?php

declare(strict_types=1);

namespace Modules\Agency\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Baris payout: satu akrual sekali bayar. */
class PayoutItem extends Model
{
    protected $table = 'agy_payout_items';

    protected $fillable = ['payout_id', 'accrual_id', 'amount_idr'];

    protected $casts = ['payout_id' => 'integer', 'accrual_id' => 'integer', 'amount_idr' => 'integer'];

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class, 'payout_id');
    }

    public function accrual(): BelongsTo
    {
        return $this->belongsTo(CommissionAccrual::class, 'accrual_id');
    }
}
