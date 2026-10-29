<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutpatientPayment extends Model
{
    protected $table = 'hsp_outpatient_payments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_idr' => 'integer',
            'cash_received_idr' => 'integer',
            'change_given_idr' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(OutpatientInvoice::class, 'invoice_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }
}
