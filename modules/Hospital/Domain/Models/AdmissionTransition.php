<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionTransition extends Model
{
    protected $table = 'hsp_admission_transitions';

    protected $fillable = [
        'admission_id',
        'from_status',
        'to_status',
        'trigger',
        'actor_id',
        'metadata',
        'transitioned_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'transitioned_at' => 'datetime',
    ];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class, 'admission_id');
    }
}
