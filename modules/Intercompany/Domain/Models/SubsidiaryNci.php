<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubsidiaryNci extends Model
{
    protected $table = 'ic_subsidiary_nci';

    protected $fillable = [
        'subsidiary_name',
        'subsidiary_entity',
        'subsidiary_entity_id',
        'parent_ownership_percent',
        'nci_ownership_percent',
        'parent_ownership_permille',
        'nci_ownership_permille',
        'net_income_idr',
        'nci_share_net_income_idr',
        'period',
    ];

    public function getSubsidiaryEntityAttribute(): ?string
    {
        return $this->attributes['subsidiary_name'] ?? null;
    }

    public function setSubsidiaryEntityAttribute(?string $val): void
    {
        $this->attributes['subsidiary_name'] = $val;
    }

    protected $casts = [
        'parent_ownership_permille' => 'integer',
        'nci_ownership_permille' => 'integer',
        'net_income_idr' => 'integer',
        'nci_share_net_income_idr' => 'integer',
    ];

    public function subsidiaryEntity(): BelongsTo
    {
        return $this->belongsTo(IntercompanyEntity::class, 'subsidiary_entity_id');
    }

    public function dividends(): HasMany
    {
        return $this->hasMany(NciDividend::class, 'subsidiary_entity_id', 'subsidiary_entity_id');
    }
}
