<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeRollbackPlan extends Model
{
    use HasFactory;

    protected $table = 'plt_rollback_plans';

    protected $fillable = [
        'change_request_id',
        'strategy',
        'estimated_rto_seconds',
        'automated',
        'playbook_steps',
        'verification_checklist',
    ];

    protected $casts = [
        'estimated_rto_seconds' => 'integer',
        'automated' => 'boolean',
        'playbook_steps' => 'array',
        'verification_checklist' => 'array',
    ];

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class, 'change_request_id');
    }
}
