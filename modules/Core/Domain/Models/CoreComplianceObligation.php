<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CoreComplianceObligation extends Model
{
    protected $table = 'core_compliance_obligations';

    protected $fillable = [
        'permit_code',
        'domain_code',
        'permit_name',
        'issuing_authority',
        'expiration_date',
        'alert_lead_days',
        'status', // VALID, EXPIRING_SOON, EXPIRED
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'alert_lead_days' => 'integer',
        'expiration_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
