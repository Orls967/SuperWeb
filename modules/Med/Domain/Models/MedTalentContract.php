<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MedTalentContract extends Model
{
    protected $table = 'med_talent_contracts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'contract_code',
        'project_id',
        'talent_party_id',
        'role_name',
        'upfront_fee_minor',
        'backend_percentage',
        'calculated_royalty_minor',
        'payout_status',
    ];

    protected $casts = [
        'upfront_fee_minor' => 'integer',
        'backend_percentage' => 'float',
        'calculated_royalty_minor' => 'integer',
    ];
}
