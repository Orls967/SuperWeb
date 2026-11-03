<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models\AgriProcessing;

use Illuminate\Database\Eloquent\Model;

class MfgAgriGenealogyEdge extends Model
{
    protected $table = 'mfg_agri_genealogy_edges';

    protected $fillable = [
        'parent_entity_type',
        'parent_entity_id',
        'child_entity_type',
        'child_entity_id',
        'quantity_grams',
    ];

    protected $casts = [
        'quantity_grams' => 'integer',
    ];
}
