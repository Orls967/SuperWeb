<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Contract\Domain\Enums\ContractType;

class ContractTemplate extends Model
{
    use HasUuids;

    protected $table = 'ctr_contract_templates';

    protected $fillable = [
        'code', 'name', 'contract_type', 'description',
        'default_clause_ids', 'required_variables', 'is_active',
    ];

    protected $casts = [
        'contract_type' => ContractType::class,
        'default_clause_ids' => 'array',
        'required_variables' => 'array',
        'is_active' => 'boolean',
    ];
}
