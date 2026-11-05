<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SybSalamDelivery extends Model
{
    protected $table = 'syb_salam_deliveries';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'salam_id',
        'delivery_batch_ref',
        'quantity_received',
        'warehouse_receipt_number',
        'inspection_status',
        'delivered_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'quantity_received' => 'integer',
        'delivered_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<SybSalamContract, $this>
     */
    public function salamContract(): BelongsTo
    {
        return $this->belongsTo(SybSalamContract::class, 'salam_id');
    }
}
