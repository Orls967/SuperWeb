<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CorporateTravelRequest extends Model
{
    protected $table = 'htl_corporate_travel_requests';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'request_code',
        'corporate_party_id',
        'employee_id',
        'employee_grade',
        'policy_budget_limit_idr',
        'requested_amount_idr',
        'approved_by_manager',
        'status',
    ];

    protected $casts = [
        'approved_by_manager' => 'boolean',
    ];
}
