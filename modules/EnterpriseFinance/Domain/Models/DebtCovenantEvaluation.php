<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtCovenantEvaluation extends Model
{
    protected $table = 'ef_debt_covenant_evaluations';

    protected $fillable = [
        'facility_id',
        'evaluation_period',
        'dscr_permille',
        'der_permille',
        'iscr_permille',
        'current_ratio_permille',
        'is_compliant',
        'sha256_signature',
        'certificate_payload_json',
        'evaluated_by',
    ];

    protected $casts = [
        'facility_id' => 'integer',
        'dscr_permille' => 'integer',
        'der_permille' => 'integer',
        'iscr_permille' => 'integer',
        'current_ratio_permille' => 'integer',
        'is_compliant' => 'boolean',
        'evaluated_by' => 'integer',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(SyndicatedFacility::class, 'facility_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
