<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SybNpfClassification extends Model
{
    protected $table = 'syb_npf_classifications';

    protected $fillable = [
        'classification_ref',
        'evaluation_date',
        'period_label',
        'total_contracts',
        'total_portfolio_minor',
        'performing_principal_minor',
        'non_performing_principal_minor',
        'total_required_provision_minor',
        'npf_gross_bps',
        'npf_net_bps',
        'is_compliant',
        'certificate_hash',
        'evaluated_by',
    ];

    protected function casts(): array
    {
        return [
            'evaluation_date' => 'date',
            'total_contracts' => 'integer',
            'total_portfolio_minor' => 'integer',
            'performing_principal_minor' => 'integer',
            'non_performing_principal_minor' => 'integer',
            'total_required_provision_minor' => 'integer',
            'npf_gross_bps' => 'integer',
            'npf_net_bps' => 'integer',
            'is_compliant' => 'boolean',
        ];
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(SybNpfSnapshot::class, 'classification_ref', 'classification_ref');
    }
}
