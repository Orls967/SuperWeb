<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** Event perubahan harga idempoten (44.6). */
class PriceEvent extends Model
{
    protected $table = 'pric_price_events';

    protected $fillable = ['event_key', 'kind', 'subject_type', 'subject_id', 'payload', 'recorded_at'];

    protected $casts = ['payload' => 'array', 'recorded_at' => 'datetime'];
}
