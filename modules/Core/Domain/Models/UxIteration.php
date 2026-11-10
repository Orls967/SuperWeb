<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UxIteration extends Model
{
    protected $table = 'core_ux_iterations';

    protected $fillable = [
        'iteration_name',
        'task_count',
        'success_count',
        'task_success_rate_bps',
        'conducted_at',
    ];

    protected $casts = [
        'task_count' => 'integer',
        'success_count' => 'integer',
        'task_success_rate_bps' => 'integer',
        'conducted_at' => 'datetime',
    ];

    public function findings(): HasMany
    {
        return $this->hasMany(UxFinding::class, 'module_code', 'iteration_name');
    }

    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('conducted_at', 'asc');
    }

    /**
     * Hitung ulang rasio keberhasilan dalam basis points (0..10.000).
     */
    public function recalculateSuccessRate(): int
    {
        if ($this->task_count <= 0) {
            $rate = 0;
        } else {
            $rate = (int) floor(($this->success_count * 10000) / $this->task_count);
        }
        $this->task_success_rate_bps = $rate;

        return $rate;
    }
}
