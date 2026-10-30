<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class InsurancePolicySequence extends Model
{
    protected $table = 'ins_policy_sequences';

    protected $fillable = [
        'product_code',
        'year',
        'last_sequence',
    ];

    protected $casts = [
        'year' => 'integer',
        'last_sequence' => 'integer',
    ];
}
