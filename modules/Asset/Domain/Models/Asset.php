<?php

declare(strict_types=1);

namespace Modules\Asset\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Asset\Domain\Enums\AssetStatus;
use Modules\Core\Domain\Models\DocumentAttachment;

/**
 * Register aset grup tunggal (30.2).
 *
 * Nilai uang menggunakan integer IDR. Domain aset tidak mengimpor
 * Mall/Logistics/Resto; konsolidasi aset lama memakai `asset_id` nullable.
 */
class Asset extends Model
{
    use HasUuids;

    protected $table = 'ast_assets';

    protected $fillable = [
        'asset_number', 'asset_tag', 'name', 'description', 'category_id',
        'location_id', 'legal_entity_id', 'responsible_user_id', 'condition', 'status',
        'brand', 'serial_number', 'acquired_at', 'in_service_at',
        'acquisition_cost_idr', 'landed_cost_idr', 'accumulated_depreciation_idr',
        'book_value_idr', 'source_type', 'source_id', 'photo_path',
    ];

    protected $casts = [
        'status' => AssetStatus::class,
        'category_id' => 'integer',
        'location_id' => 'integer',
        'responsible_user_id' => 'integer',
        'acquired_at' => 'date',
        'in_service_at' => 'date',
        'acquisition_cost_idr' => 'integer',
        'landed_cost_idr' => 'integer',
        'accumulated_depreciation_idr' => 'integer',
        'book_value_idr' => 'integer',
        'source_id' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(AssetLocation::class, 'location_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AssetEvent::class, 'asset_id')->orderBy('sequence');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class, 'asset_id')->orderByDesc('checked_out_at');
    }

    public function insurances(): HasMany
    {
        return $this->hasMany(AssetInsurance::class, 'asset_id')->orderByDesc('end_date');
    }

    public function stocktakes(): HasMany
    {
        return $this->hasMany(AssetStocktake::class, 'asset_id')->orderByDesc('created_at');
    }

    public function documents()
    {
        return $this->morphMany(DocumentAttachment::class, 'documentable');
    }

    /**
     * Pastikan book value sama dengan biaya perolehan + landed cost - depresiasi akumulatif.
     */
    public function recomputeBookValue(): int
    {
        $this->book_value_idr = max(
            0,
            (int) $this->acquisition_cost_idr
                + (int) $this->landed_cost_idr
                - (int) $this->accumulated_depreciation_idr
        );

        return $this->book_value_idr;
    }
}
