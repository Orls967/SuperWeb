<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Shared\Domain\Traits\HasUuid;

class ApprovalRequest extends Model
{
    use HasUuid;

    protected $table = 'core_approvals';

    protected $fillable = [
        'uuid',
        'approvable_type',
        'approvable_id',
        'approval_type',
        'title',
        'amount',
        'currency',
        'created_by',
        'status',
        'current_step',
        'total_steps',
        'sla_due_at',
        'decided_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'current_step' => 'integer',
        'total_steps' => 'integer',
        'sla_due_at' => 'datetime',
        'decided_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class, 'approval_id')->orderBy('step_number', 'asc');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ApprovalHistory::class, 'approval_id')->latest('id');
    }

    public function currentStepModel(): ?ApprovalStep
    {
        return $this->steps()->where('step_number', $this->current_step)->first();
    }

    public function isFullyApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
