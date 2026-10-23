<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EduContingentContract extends Model
{
    protected $table = 'edu_contingent_contracts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'contract_code',
        'worker_id',
        'client_entity_id',
        'project_code',
        'contract_max_hours',
        'hours_rendered',
        'hourly_rate_minor',
        'valid_until',
        'status',
    ];

    protected $casts = [
        'contract_max_hours' => 'integer',
        'hours_rendered' => 'integer',
        'hourly_rate_minor' => 'integer',
        'valid_until' => 'date',
    ];
}
