<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyBranchLot extends Model
{
    protected $table = 'hsp_branch_inventory_lots';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost_idr' => 'integer',
            'expiry_date' => 'date',
            'is_quarantined' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(PharmacyBranch::class, 'branch_id');
    }
}
