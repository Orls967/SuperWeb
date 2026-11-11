<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TaxReconSummary extends Model
{
    use HasUuids;

    protected $table = 'ef_tax_recon_summaries';

    protected $fillable = [
        'id',
        'entity_code',
        'period',
        'vat_output_minor',
        'vat_input_minor',
        'net_payable_minor',
        'settlement_status',
        'spt_draft_number',
    ];

    protected $casts = [
        'vat_output_minor' => 'integer',
        'vat_input_minor' => 'integer',
        'net_payable_minor' => 'integer',
    ];
}

