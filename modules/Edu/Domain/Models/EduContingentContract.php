<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EduContingentContract extends Model
{
    protected $table = 'edu_contingent_contracts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'contract_max_hours' => 'integer',
        'hours_rendered' => 'integer',
        'hourly_rate_minor' => 'integer',
        'valid_until' => 'date',
    ];
}
