<?php

namespace Modules\Epc\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EpcProject extends Model
{
    use HasUuids;

    protected $table = 'epc_projects';

    protected $fillable = [
        'project_code',
        'project_name',
        'project_type',
        'client_entity_id',
        'total_rab_budget_idr',
        'target_physical_progress_pct',
        'actual_physical_progress_pct',
        'accumulated_cip_cost_idr',
        'capitalized_asset_value_idr',
        'start_date',
        'target_completion_date',
        'status',
    ];

    protected $casts = [
        'total_rab_budget_idr' => 'integer',
        'accumulated_cip_cost_idr' => 'integer',
        'capitalized_asset_value_idr' => 'integer',
        'target_physical_progress_pct' => 'decimal:2',
        'actual_physical_progress_pct' => 'decimal:2',
        'start_date' => 'date',
        'target_completion_date' => 'date',
    ];

    public function wbsNodes(): HasMany
    {
        return $this->hasMany(EpcWbsNode::class, 'project_id');
    }

    public function progressCertificates(): HasMany
    {
        return $this->hasMany(EpcProgressCertificate::class, 'project_id');
    }

    public function capitalizations(): HasMany
    {
        return $this->hasMany(EpcCipCapitalization::class, 'project_id');
    }
}
