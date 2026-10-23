<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CloudKitchen\Application\Services\CloudKitchenService;
use Modules\CloudKitchen\Domain\Models\CateringOrder;
use Modules\CloudKitchen\Domain\Models\CloudKitchen;
use Tests\TestCase;

class CloudKitchenTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected CloudKitchen $kitchen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->kitchen = CloudKitchen::create([
            'kitchen_code' => 'CK-SAT-001',
            'name' => 'Banjarmasin Central Kitchen Satellite',
            'type' => 'satellite',
            'hourly_capacity' => 2,
            'current_hourly_load' => 0,
            'service_radius_km' => 15.0,
            'is_active' => true,
        ]);

        $this->seed(LedgerAccountsSeeder::class);
    }

    /**
     * DoR 74.7a: Kuota harian habis -> tolak order berikutnya.
     */
    public function test_daily_quota_exhausted_rejects_subsequent_order(): void
    {
        /** @var CloudKitchenService $service */
        $service = app(CloudKitchenService::class);

        $startDate = Carbon::parse('2026-10-01');
        $sub = $service->createSubscription(
            userId: $this->user->id,
            packageName: 'lunch_5d',
            dailyQuota: 1,
            monthlyFeeIdr: 600000,
            dailyMealPriceIdr: 30000,
            startDate: $startDate
        );

        $deliveryDate = Carbon::parse('2026-10-05');
        $order1 = $service->dispatchMealOrder($sub, 'lunch', $deliveryDate);
        $this->assertSame('dispatched', $order1->status);

        // Percobaan dispatch order kedua di hari yang sama wajib ditolak
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Daily catering meal quota exceeded for this subscription.');
        $service->dispatchMealOrder($sub, 'dinner', $deliveryDate);
    }

    /**
     * DoR 74.7b: Deduction payroll = konsumsi tercatat.
     */
    public function test_catering_payroll_deduction_equals_recorded_consumption(): void
    {
        /** @var CloudKitchenService $service */
        $service = app(CloudKitchenService::class);

        $this->kitchen->update(['hourly_capacity' => 10]);

        $startDate = Carbon::parse('2026-10-01');
        $monthlyFee = 600000;
        $mealPrice = 30000;

        $sub = $service->createSubscription(
            userId: $this->user->id,
            packageName: 'lunch_5d',
            dailyQuota: 1,
            monthlyFeeIdr: $monthlyFee,
            dailyMealPriceIdr: $mealPrice,
            startDate: $startDate
        );

        $this->assertSame('active', $sub->status);
        $this->assertSame($monthlyFee, $sub->monthly_fee_idr);

        // Verifikasi transaksi ledger payroll deduction tercatat
        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'hcm_meal_payroll_deduction',
            'reference_type' => 'catering_subscription',
            'reference_id' => (string) $sub->id,
        ]);

        // Dispatch 3 meal orders pada tanggal berbeda
        $order1 = $service->dispatchMealOrder($sub, 'lunch', Carbon::parse('2026-10-05'));
        $order2 = $service->dispatchMealOrder($sub, 'lunch', Carbon::parse('2026-10-06'));
        $order3 = $service->dispatchMealOrder($sub, 'lunch', Carbon::parse('2026-10-07'));

        $totalConsumed = CateringOrder::where('subscription_id', $sub->id)->sum('meal_cost_idr');
        $this->assertSame(90000, (int) $totalConsumed);
        $this->assertSame($mealPrice, $order1->meal_cost_idr);
        $this->assertSame($mealPrice, $order2->meal_cost_idr);
        $this->assertSame($mealPrice, $order3->meal_cost_idr);
    }

    /**
     * DoR 74.7c: Refund outlet tutup masuk gaji berikutnya (refund ledger).
     */
    public function test_refund_closed_outlet_credits_next_payroll_cycle(): void
    {
        /** @var CloudKitchenService $service */
        $service = app(CloudKitchenService::class);

        $startDate = Carbon::parse('2026-10-01');
        $sub = $service->createSubscription($this->user->id, 'lunch_5d', 1, 600000, 30000, $startDate);

        $order = $service->dispatchMealOrder($sub, 'lunch', Carbon::parse('2026-10-06'));
        $this->assertSame('dispatched', $order->status);

        // Refund ketika order dibatalkan atau outlet tutup
        $service->refundCancelledMeal($order);
        $order->refresh();

        $this->assertSame('refunded_closed', $order->status);

        // Ledger refund diposting membalikkan debit/kredit
        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'catering_refund',
            'reference_type' => 'catering_order',
            'reference_id' => (string) $order->id,
        ]);
    }

    /**
     * DoR 74.7d: Assign kitchen tidak melebihi kapasitas (guardrail kapasitas).
     */
    public function test_assign_kitchen_does_not_exceed_capacity(): void
    {
        /** @var CloudKitchenService $service */
        $service = app(CloudKitchenService::class);

        // Kitchen memiliki hourly_capacity = 2
        $startDate = Carbon::parse('2026-10-01');
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $sub1 = $service->createSubscription($this->user->id, 'lunch_5d', 1, 600000, 30000, $startDate);
        $sub2 = $service->createSubscription($user2->id, 'lunch_5d', 1, 600000, 30000, $startDate);
        $sub3 = $service->createSubscription($user3->id, 'lunch_5d', 1, 600000, 30000, $startDate);

        $deliveryDate = Carbon::parse('2026-10-08');

        // Order 1: load -> 1
        $order1 = $service->dispatchMealOrder($sub1, 'lunch', $deliveryDate);
        $this->assertSame('dispatched', $order1->status);

        // Order 2: load -> 2 (mencapai kapasitas maksimum)
        $order2 = $service->dispatchMealOrder($sub2, 'lunch', $deliveryDate);
        $this->assertSame('dispatched', $order2->status);

        // Order 3: wajib ditolak karena kapasitas penuh
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No cloud kitchen currently has capacity available.');
        $service->dispatchMealOrder($sub3, 'lunch', $deliveryDate);
    }

    /**
     * DoR 74.7e: Reconcile deduction = ledger.
     */
    public function test_reconcile_catering_deductions_equals_ledger(): void
    {
        /** @var CloudKitchenService $service */
        $service = app(CloudKitchenService::class);

        $startDate = Carbon::parse('2026-10-01');
        $sub = $service->createSubscription($this->user->id, 'lunch_5d', 2, 600000, 30000, $startDate);

        $order1 = $service->dispatchMealOrder($sub, 'lunch', Carbon::parse('2026-10-05'));
        $order2 = $service->dispatchMealOrder($sub, 'dinner', Carbon::parse('2026-10-05'));

        // Refund satu order
        $service->refundCancelledMeal($order2);

        $recon = $service->reconcileCateringLedger();
        $this->assertTrue($recon['is_reconciled']);
        $this->assertSame(600000, $recon['total_subscriptions_idr']);
        $this->assertSame(30000, $recon['total_refunds_idr']);
        $this->assertSame(570000, $recon['net_expected_deduction_idr']);
        $this->assertSame(1, $recon['orders_dispatched_count']);
        $this->assertSame(30000, $recon['dispatched_meal_value_idr']);
    }
}
