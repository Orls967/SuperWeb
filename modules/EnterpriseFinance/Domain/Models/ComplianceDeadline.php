<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ComplianceDeadline extends Model
{
    protected $table = 'ef_compliance_deadlines';

    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
    ];
}
