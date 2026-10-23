<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoldenScenarioStep extends Model
{
    protected $table = 'core_golden_scenario_steps';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'amount_idr' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(GoldenScenarioRun::class, 'run_id');
    }
}
