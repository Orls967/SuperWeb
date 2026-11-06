<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EgyParameter extends Model
{
    protected $table = 'egy_parameters';

    protected $fillable = [
        'parameter_key',
        'version',
        'value',
        'unit',
        'effective_from',
        'effective_to',
        'simulation_label',
        'metadata',
        'confirmed_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'metadata' => 'array',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $param = static::where('parameter_key', $key)
            ->orderBy('version', 'desc')
            ->first();

        if ($param === null) {
            return $default;
        }

        $val = $param->value;
        if (is_numeric($val)) {
            return str_contains($val, '.') ? (float) $val : (int) $val;
        }

        return $val;
    }
}
