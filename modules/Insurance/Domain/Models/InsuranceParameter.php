<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Insurance\Domain\Concerns\AppendOnly;

class InsuranceParameter extends Model
{
    use AppendOnly;

    protected $table = 'ins_parameters';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'version',
        'effective_from',
        'source',
        'simulation_label',
        'value',
        'is_fixture',
        'owner_confirmed_at',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_fixture' => 'boolean',
            'effective_from' => 'date',
            'owner_confirmed_at' => 'datetime',
            'version' => 'integer',
        ];
    }
}
