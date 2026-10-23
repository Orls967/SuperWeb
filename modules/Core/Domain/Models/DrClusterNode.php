<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrClusterNode extends Model
{
    protected $table = 'core_dr_cluster_nodes';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'replication_lag_ms' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function drill(): BelongsTo
    {
        return $this->belongsTo(DrFailoverDrill::class, 'drill_id');
    }
}
