<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Enums\ViolationStatus;

/**
 * Append-only fact log of state machine transitions for boundary violations (DR4-1, W8-D43).
 */
class CoreBoundaryViolationTransition extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'core_boundary_violation_transitions';

    protected $fillable = [
        'violation_id',
        'from_status',
        'to_status',
        'actor',
        'reason',
        'commit_sha',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => ViolationStatus::class,
            'to_status' => ViolationStatus::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CoreBoundaryViolation, CoreBoundaryViolationTransition>
     */
    public function violation(): BelongsTo
    {
        return $this->belongsTo(CoreBoundaryViolation::class, 'violation_id');
    }
}
