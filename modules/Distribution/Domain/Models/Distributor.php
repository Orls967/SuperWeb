<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Entitas jaringan distribusi: distributor/sub-distributor/agen/dealer. */
class Distributor extends Model
{
    use HasUuids;

    protected $table = 'dist_distributors';

    protected $fillable = [
        'code', 'name', 'kind', 'parent_id', 'party_id', 'owner_user_id', 'outlet_code',
        'tier', 'status', 'payment_terms_days', 'credit_limit_idr', 'credit_exposure_idr',
        'approved_at', 'approval_id', 'notes',
    ];

    protected $casts = [
        'owner_user_id' => 'integer', 'payment_terms_days' => 'integer',
        'credit_limit_idr' => 'integer', 'credit_exposure_idr' => 'integer',
        'approval_id' => 'integer',
        'approved_at' => 'date',
    ];

    public const STATUSES = ['onboarding', 'approved', 'suspended', 'blocked', 'terminated'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function outlets(): HasMany
    {
        return $this->hasMany(DistOutlet::class, 'distributor_id');
    }

    public function arInvoices(): HasMany
    {
        return $this->hasMany(ArInvoice::class, 'distributor_id');
    }

    public function securities(): HasMany
    {
        return $this->hasMany(Security::class, 'distributor_id');
    }

    public function coverages(): HasMany
    {
        return $this->hasMany(TerritoryCoverage::class, 'distributor_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(Target::class, 'distributor_id');
    }

    public function scorecards(): HasMany
    {
        return $this->hasMany(Scorecard::class, 'distributor_id');
    }

    public function availableCredit(): int
    {
        return max(0, (int) $this->credit_limit_idr - (int) $this->credit_exposure_idr);
    }

    /** 42.4 Blokir: lewat limit, lewat termin, atau status non-aktif. */
    public function isBlocked(): bool
    {
        if (in_array($this->status, ['blocked', 'suspended', 'terminated'], true)) {
            return true;
        }

        return (int) $this->credit_limit_idr > 0
            && (int) $this->credit_exposure_idr >= (int) $this->credit_limit_idr;
    }

    public function canOrder(int $amountIdr): bool
    {
        return $this->status === 'approved' && ! $this->isBlocked() && $amountIdr <= $this->availableCredit();
    }
}
