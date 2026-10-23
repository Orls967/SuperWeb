<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CoreEventSchema extends Model
{
    protected $table = 'core_event_schemas';

    protected $fillable = [
        'event_name',
        'version',
        'producer_module',
        'consumer_modules',
        'payload_schema',
        'description',
    ];

    protected $casts = [
        'version' => 'integer',
        'consumer_modules' => 'array',
        'payload_schema' => 'array',
    ];
}
