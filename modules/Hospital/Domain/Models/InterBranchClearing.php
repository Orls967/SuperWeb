<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterBranchClearing extends Model
{
    protected $table = 'hsp_inter_branch_clearings';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_idr' => 'integer',
        ];
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(PharmacyBranch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(PharmacyBranch::class, 'to_branch_id');
    }
}
