<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Syariah\Domain\Enums\WaqfType;

/**
 * Model Penerimaan Pokok Wakaf Abadi Uang & Aset (D162-5).
 */
final class SybWaqfPrincipal extends Model
{
    protected $table = 'syb_waqf_principals';

    protected $fillable = [
        'wakif_user_id',
        'waqf_type',
        'principal_amount_idr',
        'asset_description',
        'purpose',
        'is_perpetual',
        'certificate_number',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'wakif_user_id' => 'integer',
        'waqf_type' => WaqfType::class,
        'principal_amount_idr' => 'integer',
        'is_perpetual' => 'boolean',
    ];

    public function wakif(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wakif_user_id');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(SybWaqfDistribution::class, 'waqf_principal_id');
    }
}

