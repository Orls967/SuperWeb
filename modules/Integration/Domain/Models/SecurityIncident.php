<?php

declare(strict_types=1);

namespace Modules\Integration\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityIncident extends Model
{
    protected $table = 'sec_security_incidents';

    protected $fillable = [
        'incident_code',
        'incident_class',
        'severity',
        'status',
        'affected_lines',
        'description',
        'playbook_steps',
        'detected_at',
        'contained_at',
        'resolved_at',
        'postmortem',
        'capa',
        'regulator_report',
    ];

    protected $casts = [
        'affected_lines' => 'array',
        'playbook_steps' => 'array',
        'detected_at' => 'datetime',
        'contained_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public const CLASS_RANSOMWARE = 'RANSOMWARE';

    public const CLASS_DATA_LEAK = 'DATA_LEAK';

    public const CLASS_PAYMENT_FRAUD = 'PAYMENT_FRAUD';

    public const CLASS_UNAUTH_ACCESS = 'UNAUTHORIZED_ACCESS';

    public const SEV_P1 = 'P1'; // Critical: Revenue/data at risk

    public const SEV_P2 = 'P2'; // High: Partial outage

    public const SEV_P3 = 'P3'; // Medium: Degraded

    public const SEV_P4 = 'P4'; // Low: Minor

    public const STATUS_DETECTED = 'DETECTED';

    public const STATUS_INVESTIGATING = 'INVESTIGATING';

    public const STATUS_CONTAINED = 'CONTAINED';

    public const STATUS_RESOLVED = 'RESOLVED';

    public const STATUS_CLOSED = 'CLOSED';

    public function mttr(): ?float
    {
        if ($this->detected_at && $this->resolved_at) {
            return $this->detected_at->diffInMinutes($this->resolved_at);
        }

        return null;
    }
}
