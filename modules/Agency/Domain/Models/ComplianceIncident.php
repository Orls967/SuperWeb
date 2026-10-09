<?php

declare(strict_types=1);

namespace Modules\Agency\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceIncident extends Model
{
    use HasUuids;

    protected $table = 'agy_compliance_incidents';

    protected $fillable = [
        'agent_id', 'violation_type', 'severity', 'sanction', 'description',
        'status', 'appeal_notes',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }
}
