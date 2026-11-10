<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntercompanyTransactionHistory extends Model
{
    protected $table = 'ic_transaction_histories';

    protected $fillable = [
        'transaction_id',
        'from_status',
        'to_status',
        'reason',
        'operator_id',
    ];

    protected $casts = [
        'operator_id' => 'integer',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(IntercompanyTransaction::class, 'transaction_id');
    }
}
