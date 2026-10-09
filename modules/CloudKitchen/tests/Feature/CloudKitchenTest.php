<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\CloudKitchen\Application\Services\CloudKitchenService;
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

        LedgerAccount::create([
            'code' => 'hcm:meals_deduction:IDR',
            'name' => 'HCM Meals Deduction Liability',
            'asset_code' => 'IDR',
            'kind' => 'liability',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);

        LedgerAccount::create([
            'code' => 'resto:catering_revenue:IDR',
            'name' => 'Resto Catering Revenue',
            'asset_code' => 'IDR',
            'kind' => 'revenue',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);
    }

    public function test_catering_subscription_creation_and_payroll_deduction(): void
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

        $this->assertEquals('active', $sub->status);
        $this->assertEquals(600000, $sub->monthly_fee_idr);
    }

    public function test_daily_quota_enforcement(): void
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
        $this->assertEquals('dispatched', $order1->status);

        // Exceeding daily quota should throw
        $this->expectException(\InvalidArgumentException::class);
        $service->dispatchMealOrder($sub, 'dinner', $deliveryDate);
    }

    public function test_kitchen_capacity_guardrail_and_refund(): void
    {
        /** @var CloudKitchenService $service */
        $service = app(CloudKitchenService::class);

        $startDate = Carbon::parse('2026-10-01');
        $sub1 = $service->createSubscription($this->user->id, 'lunch_5d', 1, 600000, 30000, $startDate);

        $order = $service->dispatchMealOrder($sub1, 'lunch', Carbon::parse('2026-10-06'));
        $this->assertEquals('dispatched', $order->status);

        // Refund cancelled meal
        $service->refundCancelledMeal($order);
        $order->refresh();
        $this->assertEquals('refunded_closed', $order->status);
    }
}
