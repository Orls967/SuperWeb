<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DrFailoverDrill extends Model
{
    protected $table = 'core_dr_failover_drills';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rpo_target_seconds' => 'integer',
            'rto_target_seconds' => 'integer',
            'rpo_achieved_seconds' => 'integer',
            'rto_achieved_seconds' => 'integer',
            'data_loss_bytes' => 'integer',
            'executed_at' => 'datetime',
        ];
    }

    public function clusterNodes(): HasMany
    {
        return $this->hasMany(DrClusterNode::class, 'drill_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}
