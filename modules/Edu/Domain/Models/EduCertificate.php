<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EduCertificate extends Model
{
    protected $table = 'edu_certificates';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
        'cpd_points_earned' => 'integer',
    ];
}
