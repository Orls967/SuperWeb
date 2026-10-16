<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MedProject extends Model
{
    protected $table = 'med_projects';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'project_code',
        'title',
        'genre',
        'phase',
        'budget_limit_minor',
        'crew_cost_minor',
        'vendor_cost_minor',
        'studio_cost_minor',
        'total_production_cost_minor',
        'box_office_revenue_minor',
        'is_capitalized_as_asset',
    ];

    protected $casts = [
        'budget_limit_minor' => 'integer',
        'crew_cost_minor' => 'integer',
        'vendor_cost_minor' => 'integer',
        'studio_cost_minor' => 'integer',
        'total_production_cost_minor' => 'integer',
        'box_office_revenue_minor' => 'integer',
        'is_capitalized_as_asset' => 'boolean',
    ];
}
