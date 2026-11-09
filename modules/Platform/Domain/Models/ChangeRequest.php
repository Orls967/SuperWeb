<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChangeRequest extends Model
{
    use HasFactory;

    protected $table = 'plt_change_requests';

    protected $fillable = [
        'reference_number',
        'title',
        'description',
        'requester_id',
        'target_module',
        'change_type',
        'status',
        'risk_score',
        'risk_tier',
        'release_train_id',
        'scheduled_at',
        'deployed_at',
        'closed_at',
        'metadata',
    ];

    protected $casts = [
        'risk_score' => 'integer',
        'scheduled_at' => 'datetime',
        'deployed_at' => 'datetime',
        'closed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function releaseTrain(): BelongsTo
    {
        return $this->belongsTo(ReleaseTrain::class, 'release_train_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(ChangeTransition::class, 'change_request_id');
    }

    public function rollbackPlan(): HasOne
    {
        return $this->hasOne(ChangeRollbackPlan::class, 'change_request_id');
    }
}
