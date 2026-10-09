<?php

declare(strict_types=1);

namespace Modules\Plm\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlmProject extends Model
{
    use HasUuids;

    protected $table = 'plm_projects';

    protected $fillable = [
        'code',
        'name',
        'stage',
        'budget_rd_idr',
        'projected_roi_percent',
        'status',
    ];

    protected $casts = [
        'budget_rd_idr' => 'integer',
        'projected_roi_percent' => 'decimal:2',
    ];

    public function eboms(): HasMany
    {
        return $this->hasMany(EngineeringBom::class, 'project_id');
    }

    public function notebooks(): HasMany
    {
        return $this->hasMany(LabNotebook::class, 'project_id');
    }
}
