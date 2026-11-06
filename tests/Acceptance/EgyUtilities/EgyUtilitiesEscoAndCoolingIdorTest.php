<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyUtilities;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Egy\database\seeders\EgyRbacSeeder;
use Modules\Egy\Domain\Models\EscoContract;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;
use Tests\TestCase;

final class EgyUtilitiesEscoAndCoolingIdorTest extends TestCase
{
    use RefreshDatabase;

    public function test_idor_protection_for_esco_contracts_and_consolidated_bills(): void
    {
        $this->seed();
        (new EgyRbacSeeder)->run();

        $clientA = User::factory()->create();
        $clientB = User::factory()->create();

        $clientA->assignRole('customer');
        $clientB->assignRole('customer');

        // Kontrak ESCO milik Client A (party_id = clientA->id)
        $contractA = EscoContract::create([
            'contract_number' => 'ESCO-A-001',
            'facility_name' => 'Client A Facility',
            'client_party_id' => $clientA->id,
            'baseline_monthly_kwh' => 50_000,
            'baseline_tariff_idr' => 1400,
            'esco_share_bps' => 7000,
            'status' => 'active',
        ]);

        // Invoice konsolidasi milik Client A
        $invoiceA = UtilityConsolidatedInvoice::create([
            'invoice_number' => 'INV-CONS-A-001',
            'property_id' => 10,
            'customer_party_id' => $clientA->id,
            'billing_period' => '2026-11',
            'total_amount_idr' => 1_500_000,
            'status' => 'issued',
        ]);

        // Client A bisa melihat miliknya
        $this->actingAs($clientA)->get(route('egy.bills.consolidated.show', $invoiceA->id))->assertOk();
        $this->actingAs($clientA)->get(route('egy.esco.show', $contractA->id))->assertOk();

        // Client B (IDOR) mencoba melihat milik Client A -> 403 Forbidden
        $this->actingAs($clientB)->get(route('egy.bills.consolidated.show', $invoiceA->id))->assertForbidden();
        $this->actingAs($clientB)->get(route('egy.esco.show', $contractA->id))->assertForbidden();
    }
}
