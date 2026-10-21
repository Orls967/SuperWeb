<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CoreCommandCenterMetric extends Model
{
    protected $table = 'core_command_center_metrics';

    protected $fillable = [
        'metric_key',
        'domain_code',
        'metric_value_minor',
        'unit',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'metric_value_minor' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
