<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrisisIncident extends Model
{
    protected $table = 'core_crisis_incidents';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'affected_branches_count' => 'integer',
            'total_units_quarantined' => 'integer',
            'activated_at' => 'datetime',
        ];
    }

    public function affectedItems(): HasMany
    {
        return $this->hasMany(CrisisAffectedItem::class, 'incident_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }
}
