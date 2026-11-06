<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CarbonProject extends Model
{
    use HasFactory;

    protected $table = 'egy_carbon_projects';

    protected $fillable = [
        'project_code',
        'project_name',
        'project_type',
        'standard_registry',
        'registry_code',
        'developer_party_id',
        'total_certified_credits_kg_co2e',
        'available_credits_kg_co2e',
        'retired_credits_kg_co2e',
        'status',
    ];

    protected $casts = [
        'developer_party_id' => 'integer',
        'total_certified_credits_kg_co2e' => 'integer',
        'available_credits_kg_co2e' => 'integer',
        'retired_credits_kg_co2e' => 'integer',
    ];

    public function batches(): HasMany
    {
        return $this->hasMany(CarbonCreditBatch::class, 'carbon_project_id');
    }
}
