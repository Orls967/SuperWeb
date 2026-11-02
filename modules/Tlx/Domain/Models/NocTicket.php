<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NocTicket extends Model
{
    use HasUuids;

    protected $table = 'tlx_noc_tickets';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'ticket_number',
        'root_element_id',
        'severity',
        'status',
        'mttr_minutes',
        'sla_target_minutes',
        'is_sla_breached',
        'resolution_notes',
        'opened_at',
        'resolved_at',
    ];

    protected $casts = [
        'mttr_minutes' => 'integer',
        'sla_target_minutes' => 'integer',
        'is_sla_breached' => 'boolean',
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function rootElement(): BelongsTo
    {
        return $this->belongsTo(NetworkElement::class, 'root_element_id');
    }

    public function alarms(): HasMany
    {
        return $this->hasMany(NocAlarm::class, 'ticket_id');
    }
}
