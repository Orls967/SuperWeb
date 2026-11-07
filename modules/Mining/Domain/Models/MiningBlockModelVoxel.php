<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningBlockModelVoxel extends Model
{
    use HasUuids;

    protected $table = 'min_block_model_voxels';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'pit_id',
        'voxel_code',
        'center_x_mm',
        'center_y_mm',
        'center_z_mm',
        'volume_cm3',
        'density_kg_m3',
        'estimated_tonnage_kg',
        'estimated_grade_ppm',
        'classification',
        'status',
    ];

    protected $casts = [
        'center_x_mm' => 'integer',
        'center_y_mm' => 'integer',
        'center_z_mm' => 'integer',
        'volume_cm3' => 'integer',
        'density_kg_m3' => 'integer',
        'estimated_tonnage_kg' => 'integer',
        'estimated_grade_ppm' => 'integer',
    ];

    public function pit(): BelongsTo
    {
        return $this->belongsTo(MiningPit::class, 'pit_id');
    }
}
