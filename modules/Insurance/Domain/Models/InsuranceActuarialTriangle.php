<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class InsuranceActuarialTriangle extends Model
{
    protected $table = 'ins_actuarial_triangles';

    protected $fillable = [
        'line_category',
        'accident_year',
        'development_year',
        'cumulative_paid_idr',
    ];

    protected $casts = [
        'accident_year' => 'integer',
        'development_year' => 'integer',
        'cumulative_paid_idr' => 'integer',
    ];
}
