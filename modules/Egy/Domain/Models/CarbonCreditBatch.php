<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CarbonCreditBatch extends Model
{
    use HasFactory;

    protected $table = 'egy_carbon_credit_batches';

    protected $fillable = [
        'carbon_project_id',
        'batch_number',
        'vintage_year',
        'initial_quantity_kg_co2e',
        'remaining_quantity_kg_co2e',
        'issued_price_minor_per_ton',
        'verification_date',
        'expiration_date',
        'verifier_entity',
        'certificate_hash',
        'status',
    ];

    protected $casts = [
        'carbon_project_id' => 'integer',
        'initial_quantity_kg_co2e' => 'integer',
        'remaining_quantity_kg_co2e' => 'integer',
        'issued_price_minor_per_ton' => 'integer',
        'verification_date' => 'date',
        'expiration_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(CarbonProject::class, 'carbon_project_id');
    }
}
