<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class CbamDeclaration extends Model
{
    use HasFactory;

    protected $table = 'egy_cbam_declarations';

    protected $fillable = [
        'declaration_number',
        'export_contract_number',
        'container_number',
        'hs_code',
        'country_destination',
        'exporter_party_id',
        'product_tonnage_kg',
        'specific_emission_factor_bps',
        'eu_benchmark_factor_bps',
        'gross_embedded_emissions_kg_co2e',
        'net_payable_emissions_kg_co2e',
        'carbon_price_eur_minor_per_ton',
        'total_cbam_liability_eur_minor',
        'total_cbam_liability_idr',
        'ledger_transaction_id',
        'status',
        'certificate_hash',
    ];

    protected $casts = [
        'exporter_party_id' => 'integer',
        'product_tonnage_kg' => 'integer',
        'specific_emission_factor_bps' => 'integer',
        'eu_benchmark_factor_bps' => 'integer',
        'gross_embedded_emissions_kg_co2e' => 'integer',
        'net_payable_emissions_kg_co2e' => 'integer',
        'carbon_price_eur_minor_per_ton' => 'integer',
        'total_cbam_liability_eur_minor' => 'integer',
        'total_cbam_liability_idr' => 'integer',
        'ledger_transaction_id' => 'integer',
    ];
}
