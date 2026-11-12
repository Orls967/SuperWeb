<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Intercompany\Domain\Enums\IntercompanyTransactionStatus;

class IntercompanyTransaction extends Model
{
    protected $table = 'ic_transactions';

    protected $fillable = [
        'transaction_code',
        'selling_entity',
        'buying_entity',
        'selling_entity_id',
        'buying_entity_id',
        'description',
        'currency',
        'amount_idr',
        'amount_minor',
        'amount_functional_idr',
        'sales_invoice_ref',
        'purchase_bill_ref',
        'tp_rule_id',
        'status',
        'reconciled_at',
        'eliminated_at',
        'created_by',
        'approved_by',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'amount_idr' => 'integer',
        'amount_minor' => 'integer',
        'amount_functional_idr' => 'integer',
        'reconciled_at' => 'datetime',
        'eliminated_at' => 'datetime',
        'created_by' => 'integer',
        'approved_by' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->selling_entity) && ! empty($model->selling_entity_id)) {
                $model->selling_entity = IntercompanyEntity::find($model->selling_entity_id)?->entity_code ?? 'ENTITY_'.$model->selling_entity_id;
            }
            if (empty($model->buying_entity) && ! empty($model->buying_entity_id)) {
                $model->buying_entity = IntercompanyEntity::find($model->buying_entity_id)?->entity_code ?? 'ENTITY_'.$model->buying_entity_id;
            }
            if (empty($model->amount_idr) && ! empty($model->amount_minor)) {
                $model->amount_idr = $model->amount_minor;
            }
        });
    }

    public function statusEnum(): IntercompanyTransactionStatus
    {
        return IntercompanyTransactionStatus::tryFrom($this->status) ?? IntercompanyTransactionStatus::DRAFT;
    }

    public function sellingEntity(): BelongsTo
    {
        return $this->belongsTo(IntercompanyEntity::class, 'selling_entity_id');
    }

    public function buyingEntity(): BelongsTo
    {
        return $this->belongsTo(IntercompanyEntity::class, 'buying_entity_id');
    }

    public function transferPricingRule(): BelongsTo
    {
        return $this->belongsTo(TransferPricingRule::class, 'tp_rule_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(IntercompanyTransactionHistory::class, 'transaction_id');
    }
}
