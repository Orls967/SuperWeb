<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedRoyaltyStatementLine extends Model
{
    use HasUuids;

    protected $table = 'med_royalty_statement_lines';

    protected $fillable = [
        'id',
        'statement_id',
        'rightsholder_party_id',
        'rights_type',
        'share_bps',
        'gross_share_minor',
        'recoupment_deducted_minor',
        'net_payable_minor',
    ];

    protected $casts = [
        'share_bps' => 'integer',
        'gross_share_minor' => 'integer',
        'recoupment_deducted_minor' => 'integer',
        'net_payable_minor' => 'integer',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(MedRoyaltyStatement::class, 'statement_id');
    }
}
