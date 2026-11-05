<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Penilaian Zakat Mal per Muzakki per Haul (D162-4).
 */
final class SybZakatAssessment extends Model
{
    protected $table = 'syb_zakat_assessments';

    protected $fillable = [
        'customer_user_id',
        'haul_year',
        'gold_price_per_gram_idr',
        'nisab_idr',
        'qualifying_wealth_idr',
        'due_debts_idr',
        'net_zakat_base_idr',
        'holding_days',
        'haul_fulfilled',
        'reaches_nisab',
        'is_obligated',
        'zakat_amount_idr',
        'is_collected',
        'collected_at',
        'auto_debit',
        'certificate_number',
        'idempotency_key',
    ];

    protected $casts = [
        'customer_user_id' => 'integer',
        'haul_year' => 'integer',
        'gold_price_per_gram_idr' => 'integer',
        'nisab_idr' => 'integer',
        'qualifying_wealth_idr' => 'integer',
        'due_debts_idr' => 'integer',
        'net_zakat_base_idr' => 'integer',
        'holding_days' => 'integer',
        'haul_fulfilled' => 'boolean',
        'reaches_nisab' => 'boolean',
        'is_obligated' => 'boolean',
        'zakat_amount_idr' => 'integer',
        'is_collected' => 'boolean',
        'collected_at' => 'datetime',
        'auto_debit' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }
}

