<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CoreAiDecisionSnapshot extends Model
{
    protected $table = 'core_ai_decision_snapshots';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'input_snapshot' => 'array',
        'decision_output' => 'array',
    ];
}
