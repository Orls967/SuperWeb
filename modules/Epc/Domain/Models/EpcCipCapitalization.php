<?php

namespace Modules\Epc\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EpcCipCapitalization extends Model
{
    use HasUuids;

    protected $table = 'epc_cip_capitalizations';

    protected $fillable = [
        'project_id',
        'bast_number',
        'bast_type',
        'total_cip_cost_idr',
        'target_asset_category',
        'created_asset_id',
        'capitalized_at',
        'notes',
    ];

    protected $casts = [
        'total_cip_cost_idr' => 'integer',
        'capitalized_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(EpcProject::class, 'project_id');
    }
}
