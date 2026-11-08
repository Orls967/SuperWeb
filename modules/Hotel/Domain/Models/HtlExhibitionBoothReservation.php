<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HtlExhibitionBoothReservation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'htl_exhibition_booth_reservations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['signature_hash'];

    protected $casts = [
        'is_dedicated_lan' => 'boolean',
        'electricity_kva' => 'float',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(MiceContract::class, 'mice_contract_id');
    }
}
