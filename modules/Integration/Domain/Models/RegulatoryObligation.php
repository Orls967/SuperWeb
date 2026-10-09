<?php

declare(strict_types=1);

namespace Modules\Integration\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RegulatoryObligation extends Model
{
    protected $table = 'sec_regulatory_obligations';

    protected $fillable = [
        'obligation_code',
        'line_code',
        'regulation_name',
        'category',
        'due_date',
        'status',
        'escalation_level',
        'owner_email',
        'notes',
        'last_checked_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'last_checked_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_COMPLIANT = 'COMPLIANT';

    public const STATUS_OVERDUE = 'OVERDUE';

    public const STATUS_BLOCKED = 'BLOCKED';

    public function isOverdue(): bool
    {
        return $this->due_date->isPast() && $this->status !== self::STATUS_COMPLIANT;
    }

    public function shouldBlockOperations(): bool
    {
        return $this->status === self::STATUS_BLOCKED
            || ($this->isOverdue() && $this->escalation_level === 'CRITICAL');
    }
}
