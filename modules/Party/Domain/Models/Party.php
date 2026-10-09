<?php

declare(strict_types=1);

namespace Modules\Party\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Party\Domain\Enums\KybStatus;
use Modules\Party\Domain\Enums\PartyStatus;
use Modules\Party\Domain\Enums\PartyType;

class Party extends Model
{
    use HasUuids;

    protected $table = 'pty_parties';

    protected $fillable = [
        'legal_entity_id', 'type', 'name', 'name_normalized', 'short_name',
        'npwp_hash', 'npwp_masked', 'nik_hash', 'nik_masked', 'nib',
        'status', 'kyb_status', 'merged_into_id', 'merged_at', 'merge_reason', 'is_active',
    ];

    protected $casts = [
        'type' => PartyType::class,
        'status' => PartyStatus::class,
        'kyb_status' => KybStatus::class,
        'merged_at' => 'datetime',
        'merge_reason' => 'array',
        'is_active' => 'boolean',
    ];

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class, 'legal_entity_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(PartyRole::class, 'party_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(PartyAddress::class, 'party_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(PartyContact::class, 'party_id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(PartyBankAccount::class, 'party_id');
    }

    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class, 'party_id');
    }

    public function sanctionsChecks(): HasMany
    {
        return $this->hasMany(SanctionCheck::class, 'party_id');
    }

    public function creditProfile(): HasOne
    {
        return $this->hasOne(CreditProfile::class, 'party_id');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    public function isMerged(): bool
    {
        return $this->merged_into_id !== null;
    }

    public function latestSanctionCheck(): ?SanctionCheck
    {
        return $this->sanctionsChecks()->latest()->first();
    }

    public function hasRole(string $role, ?string $scopeType = null, ?string $scopeId = null): bool
    {
        return $this->roles()
            ->where('role', $role)
            ->where('is_active', true)
            ->when($scopeType, fn ($q) => $q->where('scope_type', $scopeType))
            ->when($scopeId, fn ($q) => $q->where('scope_id', $scopeId))
            ->exists();
    }

    /** Mask a sensitive identifier: show only last 4 chars. */
    public static function maskIdentifier(string $value): string
    {
        $len = strlen($value);

        return str_repeat('X', max(0, $len - 4)).substr($value, -4);
    }

    /** SHA-256 hash for dedup matching. */
    public static function hashIdentifier(string $value): string
    {
        return hash('sha256', strtoupper(preg_replace('/\D/', '', $value)));
    }
}
