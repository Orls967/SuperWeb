<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningBoreholeAssay extends Model
{
    use HasUuids;

    protected $table = 'min_borehole_assays';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'pit_id',
        'hole_code',
        'coord_x_mm',
        'coord_y_mm',
        'elevation_z_mm',
        'depth_from_mm',
        'depth_to_mm',
        'grade_ni_ppm',
        'grade_fe_ppm',
        'grade_co_ppm',
        'lithology',
    ];

    protected $casts = [
        'coord_x_mm' => 'integer',
        'coord_y_mm' => 'integer',
        'elevation_z_mm' => 'integer',
        'depth_from_mm' => 'integer',
        'depth_to_mm' => 'integer',
        'grade_ni_ppm' => 'integer',
        'grade_fe_ppm' => 'integer',
        'grade_co_ppm' => 'integer',
    ];

    public function pit(): BelongsTo
    {
        return $this->belongsTo(MiningPit::class, 'pit_id');
    }
}

