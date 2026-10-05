<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Akrual rebate per transaksi/periode → penyelesaian via approval. */
class RebateAccrual extends Model
{
    protected $table = 'dist_rebate_accruals';

    protected $fillable = [
        'distributor_id', 'program_id', 'order_id', 'period', 'base_amount_idr',
        'rate_percent', 'rebate_amount_idr', 'status', 'approval_id', 'notes',
    ];

    protected $casts = [
        'order_id' => 'string', 'period' => 'date', 'base_amount_idr' => 'integer',
        'rate_percent' => 'decimal:4', 'rebate_amount_idr' => 'integer', 'approval_id' => 'integer',
    ];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(RebateProgram::class, 'program_id');
    }
}
