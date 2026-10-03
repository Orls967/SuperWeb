<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStep extends Model
{
    protected $table = 'core_approval_steps';

    protected $fillable = [
        'approval_id',
        'step_number',
        'role_required',
        'assigned_user_id',
        'status',
        'decided_by',
        'delegated_to',
        'decided_at',
        'comments',
    ];

    protected $casts = [
        'step_number' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function approval(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function delegatee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_to');
    }
}
