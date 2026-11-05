<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class WellnessReward extends Model
{
    protected $table = 'ins_lh_wellness_rewards';

    /** @var list<string> */
    protected $fillable = [
        'user_party_id',
        'reward_type',
        'reward_value',
        'badge_title',
        'period_month',
        'is_applied',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reward_value' => 'integer',
            'period_month' => 'date',
            'is_applied' => 'boolean',
        ];
    }
}

