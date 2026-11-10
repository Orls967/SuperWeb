<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntercompanyEntity extends Model
{
    protected $table = 'ic_entities';

    protected $fillable = [
        'entity_code',
        'legal_name',
        'country_code',
        'functional_currency',
        'tax_id',
        'tax_id_npwp',
        'ownership_percent_permille',
        'is_parent',
        'is_active',
    ];

    protected $casts = [
        'ownership_percent_permille' => 'integer',
        'is_parent' => 'boolean',
        'is_active' => 'boolean',
        'tax_id_npwp' => 'encrypted',
    ];

    public function getTaxIdAttribute(): ?string
    {
        return $this->attributes['tax_id_npwp'] ?? $this->attributes['tax_id'] ?? null;
    }

    public function setTaxIdAttribute($value): void
    {
        $this->attributes['tax_id_npwp'] = $value;
    }

    public function salesTransactions(): HasMany
    {
        return $this->hasMany(IntercompanyTransaction::class, 'selling_entity_id');
    }

    public function purchaseTransactions(): HasMany
    {
        return $this->hasMany(IntercompanyTransaction::class, 'buying_entity_id');
    }

    public function loansGiven(): HasMany
    {
        return $this->hasMany(IntercompanyLoan::class, 'lender_entity_id');
    }

    public function loansTaken(): HasMany
    {
        return $this->hasMany(IntercompanyLoan::class, 'borrower_entity_id');
    }
}
