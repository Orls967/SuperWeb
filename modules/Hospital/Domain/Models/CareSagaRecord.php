<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CareSagaRecord extends Model
{
    protected $table = 'hsp_care_sagas';

    protected $fillable = [
        'saga_ref',
        'saga_type',
        'current_step',
        'status',
        'payload',
        'error_message',
        'compensation_history',
    ];

    protected $casts = [
        'payload' => 'array',
        'compensation_history' => 'array',
    ];
}
