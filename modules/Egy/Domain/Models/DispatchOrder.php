<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchOrder extends Model
{
    use HasUuids;

    protected $table = 'egy_dispatch_orders';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'dispatch_run_id',
        'generation_asset_id',
        'requested_mw',
        'dispatched_mw',
        'curtailed_mw',
        'marginal_cost_idr',
        'order_cost_thousand_idr',
        'status',
    ];

    protected $casts = [
        'requested_mw' => 'integer',
        'dispatched_mw' => 'integer',
        'curtailed_mw' => 'integer',
        'marginal_cost_idr' => 'integer',
        'order_cost_thousand_idr' => 'integer',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(DispatchRun::class, 'dispatch_run_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(GenerationAsset::class, 'generation_asset_id');
    }
}
