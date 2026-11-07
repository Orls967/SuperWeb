<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class HotelSaga extends Model
{
    protected $table = 'htl_sagas';

    protected $fillable = [
        'saga_type',
        'correlation_id',
        'current_step',
        'status',
        'payload_json',
        'step_history_json',
        'error_message',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'payload_json' => 'array',
        'step_history_json' => 'array',
    ];
}
