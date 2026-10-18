<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreDrDrillExecution extends Model
{
    protected $table = 'core_dr_drill_executions';

    protected $fillable = [
        'drill_uuid',
        'drill_type',
        'primary_region',
        'target_dr_region',
        'rpo_seconds',
        'rto_seconds',
        'status',
        'divergence_count',
        'checksum_hash',
        'metadata',
        'user_id',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'rpo_seconds' => 'integer',
            'rto_seconds' => 'integer',
            'divergence_count' => 'integer',
            'metadata' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
