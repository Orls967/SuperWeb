<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class DrugInteractionRule extends Model
{
    protected $table = 'hsp_drug_interaction_rules';

    protected $fillable = [
        'drug_a_code',
        'drug_a_name',
        'drug_b_code',
        'drug_b_name',
        'interaction_severity',
        'clinical_mechanism',
        'recommendation',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
