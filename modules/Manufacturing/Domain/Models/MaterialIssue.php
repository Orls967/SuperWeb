<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pengeluaran bahan (issue/backflush/return) — qty selalu positif. */
class MaterialIssue extends Model
{
    use HasUuids;

    protected $table = 'mfg_material_issues';

    protected $fillable = [
        'production_order_id', 'material_id', 'lot_id', 'qty', 'kind', 'method', 'alert', 'created_by_user_id',
    ];

    protected $casts = ['qty' => 'decimal:6', 'created_by_user_id' => 'integer'];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function issueLots(): HasMany
    {
        return $this->hasMany(MaterialIssueLot::class, 'material_issue_id');
    }
}
