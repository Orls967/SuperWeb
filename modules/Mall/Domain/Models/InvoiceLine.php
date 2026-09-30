<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Mall\Domain\Enums\InvoiceLineType;

class InvoiceLine extends Model
{
    protected $table = 'mall_invoice_lines';

    protected $fillable = [
        'invoice_id',
        'type',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'paid_amount',
        'status',
    ];

    protected $casts = [
        'type' => InvoiceLineType::class,
        'quantity' => 'float',
        'unit_price' => 'integer',
        'amount' => 'integer',
        'paid_amount' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function remainingAmount(): int
    {
        return max(0, $this->amount - $this->paid_amount);
    }

    public function isPaid(): bool
    {
        return $this->paid_amount >= $this->amount;
    }
}
