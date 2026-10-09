<?php

namespace Modules\Esg\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OffsetRetirement extends Model
{
    use HasUuids;

    protected $table = 'esg_offset_retirements';

    protected $fillable = [
        'retirement_number',
        'carbon_credit_id',
        'entity_id',
        'retired_quantity_tons',
        'reason',
        'retired_at',
        'certificate_url',
    ];

    protected $casts = [
        'retired_quantity_tons' => 'decimal:4',
        'retired_at' => 'date',
    ];

    public function credit(): BelongsTo
    {
        return $this->belongsTo(CarbonCredit::class, 'carbon_credit_id');
    }
}
