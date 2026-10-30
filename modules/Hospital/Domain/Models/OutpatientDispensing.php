<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutpatientDispensing extends Model
{
    protected $table = 'hsp_outpatient_dispensings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost_idr' => 'integer',
            'total_cogs_idr' => 'integer',
            'dispensed_at' => 'datetime',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(OutpatientPrescription::class, 'prescription_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(PharmacyBranchLot::class, 'lot_id');
    }

    public function dispensedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by_user_id');
    }
}
