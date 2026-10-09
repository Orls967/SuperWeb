<?php

declare(strict_types=1);

namespace Modules\Asset\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Asset\Domain\Enums\DepreciationMethod;

class Depreciation extends Model
{
    protected $table = 'ast_depreciations';

    protected $fillable = [
        'asset_id', 'period', 'method', 'book', 'amount_idr',
        'accumulated_after_idr', 'ledger_transaction_id', 'meta',
    ];

    protected $casts = [
        'method' => DepreciationMethod::class,
        'amount_idr' => 'integer',
        'accumulated_after_idr' => 'integer',
        'ledger_transaction_id' => 'integer',
        'meta' => 'array',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
}
