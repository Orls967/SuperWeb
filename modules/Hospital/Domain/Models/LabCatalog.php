<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class LabCatalog extends Model
{
    protected $table = 'hsp_lab_catalog';

    protected $fillable = [
        'test_code',
        'test_name',
        'category',
        'reference_min',
        'reference_max',
        'unit',
        'price_idr',
    ];
}
