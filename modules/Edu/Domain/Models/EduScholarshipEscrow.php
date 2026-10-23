<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EduScholarshipEscrow extends Model
{
    protected $table = 'edu_scholarship_escrows';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'escrow_code',
        'sponsor_name',
        'total_escrow_minor',
        'allocated_minor',
        'disbursed_minor',
        'status',
    ];

    protected $casts = [
        'total_escrow_minor' => 'integer',
        'allocated_minor' => 'integer',
        'disbursed_minor' => 'integer',
    ];
}
