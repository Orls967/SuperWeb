<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrisisAffectedItem extends Model
{
    protected $table = 'core_crisis_affected_items';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quarantined_quantity' => 'integer',
            'is_sale_blocked' => 'boolean',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(CrisisIncident::class, 'incident_id');
    }
}
