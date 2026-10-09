<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Contract\Domain\Enums\MilestoneStatus;

class ContractMilestone extends Model
{
    use HasUuids;

    protected $table = 'ctr_milestones';

    protected $fillable = [
        'contract_id', 'title', 'description', 'due_date',
        'responsible_role', 'status', 'completed_at',
        'completion_proof_url', 'completion_notes', 'amount_idr', 'reminder_sent',
    ];

    protected $casts = [
        'due_date' => 'date',
        'status' => MilestoneStatus::class,
        'completed_at' => 'datetime',
        'amount_idr' => 'integer',
        'reminder_sent' => 'boolean',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function isOverdue(): bool
    {
        return $this->status !== MilestoneStatus::Completed
            && $this->status !== MilestoneStatus::Waived
            && $this->due_date->isPast();
    }
}
