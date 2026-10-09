<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MedTalentContract extends Model
{
    protected $table = 'med_talent_contracts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'upfront_fee_minor' => 'integer',
        'backend_percentage' => 'float',
        'calculated_royalty_minor' => 'integer',
    ];
}
