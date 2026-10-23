<?php

declare(strict_types=1);

namespace Modules\Insurance\database\seeders;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Database\Seeder;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Insurance\Domain\Models\InsuranceClaim;
use Modules\Insurance\Domain\Models\InsurancePolicy;
use Modules\Insurance\Domain\Models\InsuranceProduct;

class InsuranceDemoSeeder extends Seeder
{
    public function run(): void
    {
        (new LedgerAccountsSeeder)->run();

        $policyHolder = User::firstOrCreate(
            ['email' => 'insured_user@example.com'],
            ['name' => 'Insured Customer', 'password' => bcrypt('secret123'), 'role' => 'customer']
        );

        // Ensure user wallet exists
        LedgerAccount::firstOrCreate(
            ['code' => "wallet:user:{$policyHolder->id}:IDR"],
            [
                'name' => "User {$policyHolder->id} Wallet IDR",
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );

        $product = InsuranceProduct::firstOrCreate(
            ['product_code' => 'INS-FLIGHT-DELAY'],
            [
                'name' => 'Automated Parametric Flight Delay Coverage',
                'premium_amount_idr' => 75000,
                'max_payout_idr' => 500000,
                'trigger_event_type' => 'FLIGHT_DELAY_EXCEEDED_2H',
                'is_active' => true,
            ]
        );

        $policy = InsurancePolicy::firstOrCreate(
            ['policy_number' => 'POL-DEMO-001'],
            [
                'product_id' => $product->id,
                'user_id' => $policyHolder->id,
                'subject_ref_type' => 'FLIGHT',
                'subject_ref_id' => 'GA-402',
                'premium_paid_idr' => 75000,
                'coverage_limit_idr' => 500000,
                'starts_at' => now()->subDay(),
                'expires_at' => now()->addDays(6),
                'status' => 'claimed',
            ]
        );

        InsuranceClaim::firstOrCreate(
            ['claim_number' => 'CLM-DEMO-001'],
            [
                'policy_id' => $policy->id,
                'trigger_event_id' => 'EVT-FLIGHT-DELAY-001',
                'payout_amount_idr' => 500000,
                'evidence_payload' => ['delay_minutes' => 145, 'status' => 'confirmed'],
                'status' => 'paid',
                'paid_at' => now(),
                'idempotency_key' => 'ins:claim:demo:001',
            ]
        );
    }
}
