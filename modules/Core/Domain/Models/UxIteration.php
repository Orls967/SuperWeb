<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UxIteration extends Model
{
    protected $table = 'core_ux_iterations';

    protected $fillable = [
        'iteration_number',
        'name',
        'iteration_name',
        'sus_score',
        'task_success_rate',
        'participants_count',
        'methodology',
        'summary',
        'task_count',
        'success_count',
        'task_success_rate_bps',
        'tested_at',
        'conducted_at',
    ];

    protected $casts = [
        'sus_score' => 'float',
        'task_success_rate' => 'float',
        'tested_at' => 'datetime',
        'conducted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (UxIteration $it) {
            if (empty($it->name) && ! empty($it->iteration_name)) {
                $it->name = $it->iteration_name;
            }
            if (empty($it->iteration_name) && ! empty($it->name)) {
                $it->iteration_name = $it->name;
            }
            if (empty($it->task_success_rate_bps) && $it->task_success_rate > 0) {
                $it->task_success_rate_bps = (int) round($it->task_success_rate * 100);
            }
        });
    }

    public function findings(): HasMany
    {
        return $this->hasMany(UxFinding::class, 'iteration_id');
    }
}
