<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChangeDeployment extends Model
{
    use HasFactory;

    protected $table = 'plt_deployments';

    protected $fillable = [
        'release_train_id',
        'environment',
        'strategy',
        'traffic_percentage',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'traffic_percentage' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function releaseTrain(): BelongsTo
    {
        return $this->belongsTo(ReleaseTrain::class, 'release_train_id');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(CanaryObservation::class, 'deployment_id');
    }
}
