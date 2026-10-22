<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoldenScenarioRun extends Model
{
    protected $table = 'core_golden_scenario_runs';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_steps' => 'integer',
            'passed_steps' => 'integer',
            'failed_steps' => 'integer',
            'total_journal_entries' => 'integer',
            'total_amount_idr' => 'integer',
            'is_balanced' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(GoldenScenarioStep::class, 'run_id');
    }
}
