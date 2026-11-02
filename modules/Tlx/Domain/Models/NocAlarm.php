<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class NocAlarm extends Model
{
    use HasUuids;

    protected $table = 'tlx_noc_alarms';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'alarm_code',
        'element_id',
        'alarm_type',
        'severity',
        'is_suppressed',
        'suppressed_by_element_id',
        'ticket_id',
        'raised_at',
    ];

    protected $casts = [
        'is_suppressed' => 'boolean',
        'raised_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (NocAlarm $alarm) {
            $immutableFields = ['alarm_code', 'element_id', 'alarm_type', 'severity', 'raised_at'];
            foreach ($immutableFields as $field) {
                if ($alarm->isDirty($field)) {
                    throw new RuntimeException("NocAlarm is append-only fact; field [{$field}] cannot be modified.");
                }
            }
        });

        static::deleting(function () {
            throw new RuntimeException('NocAlarm is append-only and cannot be deleted.');
        });
    }

    public function element(): BelongsTo
    {
        return $this->belongsTo(NetworkElement::class, 'element_id');
    }

    public function suppressedBy(): BelongsTo
    {
        return $this->belongsTo(NetworkElement::class, 'suppressed_by_element_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(NocTicket::class, 'ticket_id');
    }
}
