<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class InsuranceGrievanceTicket extends Model
{
    protected $table = 'ins_compliance_grievances';

    protected $fillable = [
        'ticket_number',
        'policy_ref',
        'complainant_party_id',
        'category',
        'grievance_text',
        'sla_deadline',
        'resolved_at',
        'resolution_summary',
        'status',
    ];

    protected $casts = [
        'sla_deadline' => 'date',
        'resolved_at' => 'datetime',
    ];
}
