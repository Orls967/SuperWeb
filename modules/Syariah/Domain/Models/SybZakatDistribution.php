<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class SybZakatDistribution extends Model
{
    protected $table = 'syb_zakat_distributions';

    protected $fillable = [
        'haul_year',
        'asnaf',
        'allocated_amount_idr',
        'distributed_amount_idr',
        'mustahik_party_ref',
        'idempotency_key',
    ];

    protected $casts = [
        'haul_year' => 'integer',
        'allocated_amount_idr' => 'integer',
        'distributed_amount_idr' => 'integer',
        'mustahik_party_ref' => 'integer',
    ];
}

