<?php

declare(strict_types=1);

namespace Modules\Supplier\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Party\Domain\Models\Party;
use Modules\Supplier\Domain\Enums\SupplierStatus;

class Supplier extends Model
{
    use HasUuids;

    protected $table = 'sup_suppliers';

    protected $fillable = [
        'party_id', 'owner_user_id', 'code', 'name', 'kind', 'status', 'lead_time_days',
        'payment_terms_days', 'rating', 'capabilities', 'factory_locations', 'notes', 'is_active',
    ];

    protected $casts = [
        'status' => SupplierStatus::class,
        'owner_user_id' => 'integer',
        'lead_time_days' => 'integer', 'payment_terms_days' => 'integer',
        'rating' => 'integer', 'capabilities' => 'array', 'factory_locations' => 'array', 'is_active' => 'boolean',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(SupplierCertification::class, 'supplier_id')->orderBy('expires_at');
    }

    public function qualifications(): HasMany
    {
        return $this->hasMany(SupplierQualification::class, 'supplier_id')->orderByDesc('created_at');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierItem::class, 'supplier_id')->where('is_active', true)->orderBy('name');
    }

    public function scorecards(): HasMany
    {
        return $this->hasMany(SupplierScorecard::class, 'supplier_id')->orderByDesc('period');
    }

    public function riskFlags(): HasMany
    {
        return $this->hasMany(SupplierRiskFlag::class, 'supplier_id')->where('is_open', true);
    }

    public function asns(): HasMany
    {
        return $this->hasMany(SupplierAsn::class, 'supplier_id')->orderByDesc('created_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SupplierDocument::class, 'supplier_id')->orderByDesc('created_at');
    }
}
