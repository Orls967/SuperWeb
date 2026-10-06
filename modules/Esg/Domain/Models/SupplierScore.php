<?php

namespace Modules\Esg\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SupplierScore extends Model
{
    use HasUuids;

    protected $table = 'esg_supplier_scores';

    protected $fillable = [
        'supplier_id',
        'evaluation_year',
        'environmental_score',
        'social_score',
        'governance_score',
        'overall_score',
        'certification_list',
        'rating_level',
        'audit_notes',
    ];

    protected $casts = [
        'environmental_score' => 'integer',
        'social_score' => 'integer',
        'governance_score' => 'integer',
        'overall_score' => 'decimal:2',
    ];
}
