<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class WellnessLog extends Model
{
    protected $table = 'ins_lh_wellness_logs';

    /** @var list<string> */
    protected $fillable = [
        'user_party_id',
        'log_date',
        'daily_steps',
        'avg_heart_rate_bpm',
        'sleep_minutes',
        'health_score',
        'is_flagged_gaming',
        'source_device_hash',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'daily_steps' => 'integer',
            'avg_heart_rate_bpm' => 'integer',
            'sleep_minutes' => 'integer',
            'health_score' => 'integer',
            'is_flagged_gaming' => 'boolean',
        ];
    }
}

