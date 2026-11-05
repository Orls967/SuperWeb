<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SybLetterOfCredit extends Model
{
    protected $table = 'syb_letters_of_credit';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'lc_number',
        'applicant_party_ref',
        'beneficiary_party_ref',
        'issuing_bank_party_ref',
        'amount_currency',
        'amount_cents',
        'margin_deposit_cents',
        'status',
        'expiry_date',
        'wakalah_fee_cents',
        'terms_conditions',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'amount_cents' => 'integer',
        'margin_deposit_cents' => 'integer',
        'wakalah_fee_cents' => 'integer',
        'expiry_date' => 'date',
    ];

    /**
     * @return HasMany<SybIstisnaContract, $this>
     */
    public function istisnaContracts(): HasMany
    {
        return $this->hasMany(SybIstisnaContract::class, 'lc_id');
    }
}
