<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UtilityConsolidatedInvoice extends Model
{
    use HasUuids;

    protected $table = 'egy_utility_consolidated_invoices';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'invoice_number',
        'property_id',
        'customer_party_id',
        'customer_id',
        'billing_period',
        'period',
        'electricity_charge_minor',
        'water_charge_minor',
        'district_cooling_charge_minor',
        'gas_charge_minor',
        'total_consolidated_minor',
        'total_amount_idr',
        'paid_amount_idr',
        'demand_charge_idr',
        'net_metering_credit_idr',
        'total_wh',
        'total_kwh_consumption',
        'peak_demand_kw',
        'due_date',
        'status',
        'journal_entry_id',
        'ledger_transaction_id',
        'idempotency_key',
    ];

    protected $casts = [
        'electricity_charge_minor' => 'integer',
        'water_charge_minor' => 'integer',
        'district_cooling_charge_minor' => 'integer',
        'gas_charge_minor' => 'integer',
        'total_consolidated_minor' => 'integer',
        'total_amount_idr' => 'integer',
        'paid_amount_idr' => 'integer',
        'demand_charge_idr' => 'integer',
        'net_metering_credit_idr' => 'integer',
        'total_wh' => 'integer',
        'total_kwh_consumption' => 'float',
        'peak_demand_kw' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->invoice_number)) {
                $model->invoice_number = 'INV-EGY-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -5));
            }
            if (empty($model->billing_period)) {
                $model->billing_period = $model->period ?? date('Y-m');
            }
            if (empty($model->period)) {
                $model->period = $model->billing_period;
            }
            if (empty($model->customer_party_id)) {
                $model->customer_party_id = 'CUST-DEFAULT';
            }
            if (empty($model->status)) {
                $model->status = 'draft';
            }
            if (! isset($model->total_amount_idr)) {
                $model->total_amount_idr = (int) (($model->total_consolidated_minor ?? 0) / 100);
            }
            if (! isset($model->total_consolidated_minor)) {
                $model->total_consolidated_minor = (int) ($model->total_amount_idr * 100);
            }
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(UtilityInvoiceLine::class, 'invoice_id');
    }
}
