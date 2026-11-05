<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SybTawarruqTransaction extends Model
{
    protected $table = 'syb_tawarruq_transactions';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'leg_number',
        'transaction_reference',
        'counterparty_name',
        'amount_cents',
        'currency',
        'executed_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'leg_number' => 'integer',
        'amount_cents' => 'integer',
        'executed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<SybTawarruqOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(SybTawarruqOrder::class, 'order_id');
    }
}
