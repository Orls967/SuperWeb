<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MiningExternalFactor extends Model
{
    use HasUuids;

    protected $table = 'min_external_factors';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'factor_key',
        'version',
        'effective_from',
        'source',
        'label',
        'numeric_value_scaled',
        'unit',
        'metadata_json',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'version' => 'integer',
        'numeric_value_scaled' => 'integer',
        'metadata_json' => 'array',
    ];
}
