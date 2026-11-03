<?php

declare(strict_types=1);

namespace Modules\Fashion\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FashionSample extends Model
{
    protected $table = 'fsh_samples';

    protected $fillable = [
        'collection_id',
        'sample_code',
        'article_name',
        'tech_pack_ref',
        'approval_status',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(FashionCollection::class, 'collection_id');
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(FashionProductionOrder::class, 'sample_id');
    }
}
