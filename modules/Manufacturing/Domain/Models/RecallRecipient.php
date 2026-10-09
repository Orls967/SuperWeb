<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Penerima terdampak recall (per item order). */
class RecallRecipient extends Model
{
    protected $table = 'mfg_recall_recipients';

    protected $fillable = [
        'recall_id', 'lot_sale_id', 'store_order_id', 'user_id', 'contact_snapshot',
        'notified_at', 'response',
    ];

    protected $casts = ['lot_sale_id' => 'integer', 'store_order_id' => 'integer', 'user_id' => 'integer', 'notified_at' => 'datetime'];

    public function recall(): BelongsTo
    {
        return $this->belongsTo(Recall::class, 'recall_id');
    }
}
