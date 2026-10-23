<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\database\seeders\AiDecisionDemoSeeder;
use Modules\Core\Domain\Models\CoreAiAnomalyQuarantine;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new AiDecisionDemoSeeder)->run();
});

test('guest is redirected to login when accessing AI governance dashboard', function (): void {
    $this->get(route('admin.ai.index'))
        ->assertRedirect(route('login'));
});

test('non-admin user is rejected with 403 Forbidden from accessing AI governance', function (): void {
    $regularUser = User::factory()->create(['role' => 'customer']);

    $this->actingAs($regularUser)
        ->get(route('admin.ai.index'))
        ->assertForbidden();
});

test('authorized admin can access AI governance dashboard and view all 4 modules', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.ai.index'))
        ->assertOk()
        ->assertSee('AI Governance', false)
        ->assertSee('Dynamic Pricing', false)
        ->assertSee('Fraud & Karantina Transaksi', false)
        ->assertSee('Preskriptif ROP & Stok', false)
        ->assertSee('Audit Trail Snapshot Keputusan', false);
});

test('admin can simulate dynamic pricing via HTTP POST', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.ai.calculate-pricing'), [
            'item_code' => 'MENU-NASI-GORENG-SPECIAL',
            'base_price_idr' => 45000,
            'current_stock' => 30,
            'hours_to_expiry' => 8,
            'space_occupancy_rate' => 0.85,
            'recent_sales_velocity' => 25,
            'baseline_sales_velocity' => 15,
            'seed' => 888,
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'data' => [
                'item_code',
                'final_price_idr',
                'base_price_idr',
                'floor_price_idr',
                'ceiling_price_idr',
                'multiplier',
                'breakdown',
                'decision_hash',
                'decision_code',
            ],
        ]);
});

test('admin can screen transaction and receive quarantine outcome via HTTP POST', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.ai.screen-anomaly'), [
            'transaction_ref' => 'TX-TEST-SCREEN-01',
            'customer_ref' => 'CUST-TEST-01',
            'amount_idr' => 90000000,
            'channel' => 'API_GATEWAY',
            'recent_tx_count_10m' => 8,
            'avg_historical_amount_idr' => 1000000,
            'is_late_night' => true,
            'is_split_bill_pattern' => true,
            'seed' => 777,
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.is_quarantined', true);
});

test('admin can release quarantined transaction with justification', function (): void {
    $admin = User::factory()->create(['name' => 'Supervisor Chief', 'role' => 'admin']);

    $quarantine = CoreAiAnomalyQuarantine::where('status', 'QUARANTINED')->first();
    expect($quarantine)->not->toBeNull();

    $response = $this->actingAs($admin)
        ->postJson(route('admin.ai.release-quarantine', $quarantine), [
            'release_justification' => 'Verifikasi video call pelanggan telah selesai dan sah.',
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'RELEASED')
        ->assertJsonPath('data.released_by', 'Supervisor Chief');

    $quarantine->refresh();
    expect($quarantine->status)->toBe('RELEASED')
        ->and($quarantine->release_justification)->toBe('Verifikasi video call pelanggan telah selesai dan sah.');
});
