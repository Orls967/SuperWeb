<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EduInternalTransfer extends Model
{
    protected $table = 'edu_internal_transfers';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'transfer_code',
        'employee_id',
        'from_entity_id',
        'to_entity_id',
        'effective_date',
        'base_salary_minor',
        'payroll_processed',
        'status',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'base_salary_minor' => 'integer',
        'payroll_processed' => 'boolean',
    ];
}
