<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LcDocument extends Model
{
    use HasUuids;

    protected $table = 'tf_lc_documents';

    protected $fillable = [
        'letter_of_credit_id',
        'doc_name',
        'document_number',
        'has_discrepancy',
        'discrepancy_details',
        'is_waived_by_applicant',
    ];

    protected $casts = [
        'has_discrepancy' => 'boolean',
        'is_waived_by_applicant' => 'boolean',
    ];

    public function letterOfCredit(): BelongsTo
    {
        return $this->belongsTo(LetterOfCredit::class, 'letter_of_credit_id');
    }
}
