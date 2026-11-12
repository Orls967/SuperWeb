<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InterEntityAssetTransfer extends Model
{
    protected $table = 'ic_inter_entity_transfers';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'transfer_code',
        'asset_tag',
        'source_entity_code',
        'target_entity_code',
        'net_book_value_minor',
        'accum_depr_minor',
        'transfer_price_minor',
        'gain_loss_minor',
        'status',
        'authorized_by_ref',
    ];

    protected $casts = [
        'net_book_value_minor' => 'integer',
        'accum_depr_minor' => 'integer',
        'transfer_price_minor' => 'integer',
        'gain_loss_minor' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
