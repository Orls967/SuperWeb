<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Enums\ViolationCategory;
use Modules\Core\Domain\Enums\ViolationStatus;

/**
 * Persisted fact of an architectural boundary violation (DR4-1, W8-D43).
 */
class CoreBoundaryViolation extends Model
{
    use HasFactory;

    protected $table = 'core_boundary_violations';

    protected $fillable = [
        'rule_id',
        'category',
        'file_path',
        'line_number',
        'signature',
        'client_module',
        'owner_module',
        'status',
        'contract_name',
        'adr_anchor',
        'waiver_reason',
        'waived_by',
        'waived_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'category' => ViolationCategory::class,
            'status' => ViolationStatus::class,
            'waived_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<CoreBoundaryViolationTransition>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(CoreBoundaryViolationTransition::class, 'violation_id')->orderBy('occurred_at');
    }

    public function isWaived(): bool
    {
        return $this->status === ViolationStatus::WAIVED;
    }

    public function isWaiverExpired(): bool
    {
        return $this->isWaived() && $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isClosed(): bool
    {
        return $this->status === ViolationStatus::CLOSED;
    }
}

