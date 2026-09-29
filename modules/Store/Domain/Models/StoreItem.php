<?php

declare(strict_types=1);

namespace Modules\Store\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class StoreItem extends Model
{
    use HasFactory;

    protected $table = 'store_items';

    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'specifications',
    ];

    protected $casts = [
        'specifications' => 'array',
    ];

    public function product(): MorphOne
    {
        return $this->morphOne(Product::class, 'productable');
    }
}
