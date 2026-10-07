<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class HotelFolioItem extends Model
{
    protected $table = 'htl_folio_items';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'folio_id',
        'item_category',
        'description',
        'amount_idr',
    ];

    protected $casts = [
        'amount_idr' => 'integer',
    ];
}
