<?php

declare(strict_types=1);

namespace Modules\Telematics\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TelematicsSequenceGap extends Model
{
    protected $table = 'oto_telematics_sequence_gaps';

    protected $fillable = [
        'device_id',
        'expected_sequence',
        'received_sequence',
        'detected_at',
    ];

    protected $casts = [
        'expected_sequence' => 'integer',
        'received_sequence' => 'integer',
        'detected_at' => 'datetime',
    ];
}
