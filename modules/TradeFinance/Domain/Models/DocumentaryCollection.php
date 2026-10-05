<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DocumentaryCollection extends Model
{
    use HasUuids;

    protected $table = 'tf_documentary_collections';

    protected $fillable = [
        'collection_number',
        'type',
        'drawee_name',
        'drawer_name',
        'collecting_bank',
        'currency',
        'amount_foreign',
        'tenor_days',
        'status',
    ];

    protected $casts = [
        'amount_foreign' => 'integer',
        'tenor_days' => 'integer',
    ];
}
