<?php

declare(strict_types=1);

namespace Modules\Rwa\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RwaCrossLineAssetMapping extends Model
{
    protected $table = 'rwa_cross_line_asset_mappings';

    protected $fillable = [
        'rwa_asset_id',
        'source_domain', // HOTEL, MALL, LOGISTICS, MINING, HOSPITAL
        'source_entity_id',
        'asset_type_code',
        'annual_yield_target_bps',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'annual_yield_target_bps' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(RwaAsset::class, 'rwa_asset_id');
    }
}
