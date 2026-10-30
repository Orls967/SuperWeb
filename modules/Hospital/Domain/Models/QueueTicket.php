<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QueueTicket extends Model
{
    protected $table = 'hsp_queues';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'queue_date' => 'date',
            'queue_number' => 'integer',
        ];
    }

    public function polyclinic(): BelongsTo
    {
        return $this->belongsTo(Polyclinic::class, 'polyclinic_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function encounter(): HasOne
    {
        return $this->hasOne(OutpatientEncounter::class, 'queue_id');
    }
}
