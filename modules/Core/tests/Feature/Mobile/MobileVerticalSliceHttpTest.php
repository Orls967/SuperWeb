<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Domain\Models\CoreMobileDevice;
use Modules\Core\Domain\Models\CoreMobileEpodRecord;
use Modules\Core\Domain\Models\CoreMobileFieldAction;
use Modules\Core\Domain\Models\CoreMobileSyncEvent;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $this->customer = User::factory()->create([
        'role' => 'customer',
    ]);
});

test('guest cannot access mobile governance routes and is redirected', function (): void {
    $this->get(route('admin.mobile.index'))
        ->assertRedirect(route('login'));
});

test('non-admin user receives 403 forbidden on mobile governance', function (): void {
    $this->actingAs($this->customer)
        ->get(route('admin.mobile.index'))
        ->assertForbidden();
});

test('admin can view mobile governance dashboard with 200 status', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.mobile.index'))
        ->assertOk()
        ->assertSee('Enterprise Mobile Suite')
        ->assertSee('Simulasikan Transmisi Antrean Offline');
});

test('admin can submit offline sync batch via POST endpoint', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.mobile.sync-batch'), [
            'device_uuid' => 'DEV-HTTP-OP-01',
            'role_scope' => 'operator',
            'event_type' => 'MFG_OUTPUT_RECORDED',
            'reference_id' => 'WO-HTTP-999',
            'operator_name' => 'Operator Test',
            'details' => json_encode(['units' => 200]),
        ])
        ->assertRedirect(route('admin.mobile.index'))
        ->assertSessionHas('success');

    expect(CoreMobileSyncEvent::where('device_uuid', 'DEV-HTTP-OP-01')->exists())->toBeTrue()
        ->and(CoreMobileDevice::where('device_uuid', 'DEV-HTTP-OP-01')->exists())->toBeTrue();
});

test('admin can submit driver epod via POST endpoint with cryptographic hash calculation', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.mobile.submit-epod'), [
            'shipment_ref' => 'SHP-HTTP-001',
            'driver_ref' => 'DRV-HTTP-01',
            'recipient_name' => 'Bambang Penerima',
            'recipient_role' => 'Logistics Staff',
            'signature_payload_b64' => 'data:image/svg+xml;base64,PHN2Zz48cGF0aCBkPSJNMTAgMTBMMjAgMjAiLz48L3N2Zz4=',
        ])
        ->assertRedirect(route('admin.mobile.index'))
        ->assertSessionHas('success');

    $record = CoreMobileEpodRecord::where('shipment_ref', 'SHP-HTTP-001')->first();
    expect($record)->not->toBeNull()
        ->and(strlen($record->signature_hash))->toBe(64)
        ->and($record->is_tampered)->toBeFalse();
});

test('admin can record field action via POST endpoint', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.mobile.record-action'), [
            'role_scope' => 'hub_operator',
            'action_type' => 'WMS_PUTAWAY',
            'reference_id' => 'BIN-HTTP-01',
            'operator_name' => 'Operator WMS',
            'notes' => 'Putaway batch 10 item',
        ])
        ->assertRedirect(route('admin.mobile.index'))
        ->assertSessionHas('success');

    expect(CoreMobileFieldAction::where('reference_id', 'BIN-HTTP-01')->exists())->toBeTrue();
});

test('admin can revoke mobile device authorization', function (): void {
    $device = CoreMobileDevice::create([
        'device_uuid' => 'DEV-REVOKE-01',
        'role_scope' => 'driver',
        'app_version' => '1.0.0',
        'platform' => 'ANDROID',
        'is_revoked' => false,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.mobile.revoke-device', $device))
        ->assertRedirect(route('admin.mobile.index'))
        ->assertSessionHas('success');

    expect($device->fresh()->is_revoked)->toBeTrue();
});
