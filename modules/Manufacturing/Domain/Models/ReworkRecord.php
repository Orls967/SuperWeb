<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Scrap/rework: alasan, biaya, flag NCR bila melebihi toleransi. */
class ReworkRecord extends Model
{
    use HasUuids;

    protected $table = 'mfg_rework_records';

    protected $fillable = [
        'production_order_id', 'material_id', 'reason', 'qty', 'kind',
        'cost_idr', 'ncr_required', 'created_by_user_id',
    ];

    protected $casts = [
        'qty' => 'decimal:6', 'cost_idr' => 'integer',
        'ncr_required' => 'boolean', 'created_by_user_id' => 'integer',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }
}
