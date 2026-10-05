<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Standar biaya per unit material pada suatu versi biaya. */
class StandardCost extends Model
{
    use HasUuids;

    protected $table = 'mfg_standard_costs';

    protected $fillable = [
        'version_id', 'material_id', 'material_cost_idr', 'conversion_cost_idr',
        'overhead_cost_idr', 'unit_cost_idr',
    ];

    protected $casts = [
        'material_cost_idr' => 'integer', 'conversion_cost_idr' => 'integer',
        'overhead_cost_idr' => 'integer', 'unit_cost_idr' => 'integer',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(CostVersion::class, 'version_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
