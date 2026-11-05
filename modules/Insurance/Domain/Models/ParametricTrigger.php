<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParametricTrigger extends Model
{
    protected $table = 'ins_param_triggers';

    /** @var list<string> */
    protected $fillable = [
        'trigger_code',
        'metric_name',
        'region_or_plot',
        'threshold_value',
        'comparison_operator',
        'payout_percentage_bps',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'threshold_value' => 'integer',
            'payout_percentage_bps' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<ParametricEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ParametricEvent::class, 'trigger_id');
    }
}
