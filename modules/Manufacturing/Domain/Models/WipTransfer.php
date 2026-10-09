<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Transfer WIP antar operasi. */
class WipTransfer extends Model
{
    protected $table = 'mfg_wip_transfers';

    protected $fillable = [
        'production_order_id', 'from_operation_id', 'to_operation_id',
        'qty', 'status', 'created_by_user_id',
    ];

    protected $casts = ['qty' => 'decimal:6', 'created_by_user_id' => 'integer'];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }
}
