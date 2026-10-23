<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ComplianceDeadline extends Model
{
    protected $table = 'ef_compliance_deadlines';

    protected $fillable = [
        'item_code',
        'title',
        'regulatory_body',
        'due_date',
        'assigned_role',
        'status',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];
}
