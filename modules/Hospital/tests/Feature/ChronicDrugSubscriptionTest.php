<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Hospital\Application\Services\ChronicDrugSubscriptionService;
use Modules\Hospital\Domain\Models\ChronicSubscription;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\Models\PharmacyBranchLot;
use Tests\TestCase;

class ChronicDrugSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private ChronicDrugSubscriptionService $subscriptionService;

    private Patient $patient;

    private PharmacyBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionService = app(ChronicDrugSubscriptionService::class);

        // Seed core ledger accounts
        $this->seed(LedgerAccountsSeeder::class);

        $this->patient = Patient::create([
            'mrn' => 'MRN-CHRONIC-01',
            'name' => 'Budi Chronic Patient',
            'date_of_birth' => '1965-08-12',
            'blood_type' => 'B+',
            'passport_hash' => hash('sha256', 'PASSPORT-MRN-CHRONIC-01'),
        ]);

        $this->branch = PharmacyBranch::create([
            'branch_code' => 'BR-CHRONIC-01',
            'name' => 'Apotek Komunitas Medika',
            'type' => 'INTERNAL',
            'city' => 'Jakarta',
            'is_active' => true,
        ]);
    }

    public function test_chronic_subscription_registration_and_recurring_batch_refill(): void
    {
        // 1. Stock available in branch
        PharmacyBranchLot::create([
            'branch_id' => $this->branch->id,
            'drug_code' => 'AMLODIPINE-10',
            'drug_name' => 'Amlodipine 10mg',
            'lot_number' => 'LOT-AMLO-99',
            'quantity' => 120,
            'expiry_date' => now()->addYear()->toDateString(),
            'is_quarantined' => false,
        ]);

        // 2. Register chronic subscription (30 tablets monthly at Rp 3.000 / tab)
        $sub = $this->subscriptionService->registerSubscription(
            patient: $this->patient,
            branch: $this->branch,
            drugCode: 'AMLODIPINE-10',
            drugName: 'Amlodipine 10mg',
            monthlyQty: 30,
            unitPriceIdr: 3000
        );

        $this->assertInstanceOf(ChronicSubscription::class, $sub);
        $this->assertSame('ACTIVE', $sub->status);
        $this->assertSame(30, $sub->monthly_quantity);

        // 3. Process refill execution
        $generatedOrders = $this->subscriptionService->processMonthlyRefills(now()->toDateString());

        $this->assertCount(1, $generatedOrders);
        $order = $generatedOrders[0];
        $this->assertSame(90000, (int) $order->total_price_idr);
        $this->assertTrue((bool) $order->is_chronic_subscription);

        // 4. Verify stock was decremented correctly via FEFO
        $remainingStock = (int) PharmacyBranchLot::where('lot_number', 'LOT-AMLO-99')->value('quantity');
        $this->assertSame(90, $remainingStock);

        // 5. Verify double-entry ledger balanced posting
        $ar = LedgerAccount::where('code', 'hsp:epharmacy_receivable:IDR')->first();
        $rev = LedgerAccount::where('code', 'hsp:epharmacy_revenue:IDR')->first();
        $this->assertNotNull($ar);
        $this->assertNotNull($rev);
        $this->assertEquals('-90000', (string) $ar->cached_balance);
        $this->assertEquals('90000', (string) $rev->cached_balance);

        // 6. Verify subscription next_refill_date was advanced by 30 days
        $this->assertSame(
            now()->addDays(30)->toDateString(),
            $sub->fresh()->next_refill_date->toDateString()
        );
    }
}
