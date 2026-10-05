<?php

declare(strict_types=1);

namespace Modules\Agency\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandAgency extends Model
{
    use HasUuids;

    protected $table = 'agy_brand_agencies';

    protected $fillable = [
        'agent_id', 'brand_name', 'principal_country', 'has_import_rights',
        'has_warranty_service', 'service_network_ref', 'effective_from',
        'effective_until', 'status',
    ];

    protected $casts = [
        'has_import_rights' => 'boolean',
        'has_warranty_service' => 'boolean',
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }
}
