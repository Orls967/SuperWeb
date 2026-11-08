<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HtlBanquetOrder extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'htl_banquet_beo_orders';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['signature_hash'];

    protected $casts = [
        'recipe_bom_json' => 'array',
        'safety_buffer_ratio' => 'float',
        'event_duration_hours' => 'float',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(MiceContract::class, 'mice_contract_id');
    }
}
