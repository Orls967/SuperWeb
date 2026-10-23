<?php

declare(strict_types=1);

namespace Modules\Proptech\Domain\Models\Bim;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TwinComponent extends Model
{
    protected $table = 'prp_twin_components';

    protected $fillable = [
        'bim_model_id',
        'component_code',
        'wbs_node_code',
        'category',
        'name',
        'location_spec',
        'status',
        'completion_pct',
        'linked_asset_id',
        'linked_sensor_id',
    ];

    protected $casts = [
        'completion_pct' => 'integer',
    ];

    public function bimModel(): BelongsTo
    {
        return $this->belongsTo(BimModel::class, 'bim_model_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(TwinIssue::class, 'component_id');
    }
}
