<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\database\seeders;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Database\Seeder;
use Modules\CloudKitchen\Domain\Models\CateringOrder;
use Modules\CloudKitchen\Domain\Models\CateringSubscription;
use Modules\CloudKitchen\Domain\Models\CloudKitchen;

class CloudKitchenDemoSeeder extends Seeder
{
    public function run(): void
    {
        (new LedgerAccountsSeeder)->run();

        $user = User::firstOrCreate(
            ['email' => 'kitchen_client@autoserve.test'],
            [
                'name' => 'Corporate Catering Client',
                'password' => bcrypt('secret'),
                'role' => 'customer',
            ]
        );

        $kitchen = CloudKitchen::firstOrCreate(
            ['kitchen_code' => 'CK-SCBD-01'],
            [
                'name' => 'SCBD Central Cloud Kitchen',
                'type' => 'central',
                'hourly_capacity' => 150,
                'current_hourly_load' => 12,
                'service_radius_km' => 10.0,
                'is_active' => true,
            ]
        );

        $subscription = CateringSubscription::firstOrCreate(
            ['subscription_code' => 'SUB-DEMO-001'],
            [
                'user_id' => $user->id,
                'package_name' => 'Corporate Executive Lunch 30D',
                'daily_quota' => 2,
                'used_quota_today' => 0,
                'monthly_fee_idr' => 1500000,
                'daily_meal_price_idr' => 50000,
                'starts_at' => now()->startOfMonth(),
                'expires_at' => now()->addMonth(),
                'status' => 'active',
            ]
        );

        CateringOrder::firstOrCreate(
            ['order_code' => 'ORD-DEMO-001'],
            [
                'subscription_id' => $subscription->id,
                'kitchen_id' => $kitchen->id,
                'delivery_date' => now()->toDateString(),
                'meal_slot' => 'lunch',
                'status' => 'dispatched',
                'meal_cost_idr' => 50000,
                'idempotency_key' => 'ck:seed:demo:001',
            ]
        );
    }
}
