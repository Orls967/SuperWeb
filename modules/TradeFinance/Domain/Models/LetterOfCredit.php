<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LetterOfCredit extends Model
{
    use HasUuids;

    protected $table = 'tf_letters_of_credit';

    protected $fillable = [
        'lc_number',
        'type',
        'issuing_bank',
        'advising_bank',
        'applicant_name',
        'beneficiary_name',
        'currency',
        'amount_foreign',
        'amount_functional_idr',
        'issue_date',
        'expiry_date',
        'tenor_days',
        'status',
    ];

    protected $casts = [
        'amount_foreign' => 'integer',
        'amount_functional_idr' => 'integer',
        'tenor_days' => 'integer',
        'issue_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(LcDocument::class, 'letter_of_credit_id');
    }
}
