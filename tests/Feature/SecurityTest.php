<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\VerifyPinAction;
use Modules\Banking\Domain\Exceptions\InvalidPinException;
use Modules\Banking\Domain\Exceptions\PinLockedException;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Mall\Domain\Enums\InvoiceStatus;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Enums\RentModel;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\Unit;
use Modules\Store\Domain\Models\Order as StoreOrder;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // ============================================================
    // 1. IDOR (INSECURE DIRECT OBJECT REFERENCE) PROTECTION
    // ============================================================

    public function test_tenant_cannot_view_another_tenants_invoice_via_portal(): void
    {
        $property = Property::first();

        // Buat Tenant A
        $userA = User::factory()->create(['role' => 'tenant']);
        $tenantA = Tenant::create([
            'property_id' => $property->id,
            'user_id' => $userA->id,
            'company_name' => 'PT Tenant Alpha Sejahtera',
            'brand_name' => 'Alpha Brand',
            'category' => 'fnb',
            'pic_name' => 'Alpha Person',
            'pic_email' => 'alpha@test.com',
            'pic_phone' => '0811111111',
            'is_active' => true,
        ]);

        // Buat Tenant B
        $userB = User::factory()->create(['role' => 'tenant']);
        $tenantB = Tenant::create([
            'property_id' => $property->id,
            'user_id' => $userB->id,
            'company_name' => 'PT Tenant Beta Nusantara',
            'brand_name' => 'Beta Brand',
            'category' => 'fashion',
            'pic_name' => 'Beta Person',
            'pic_email' => 'beta@test.com',
            'pic_phone' => '0822222222',
            'is_active' => true,
        ]);

        $units = Unit::take(2)->get();

        $leaseA = Lease::create([
            'lease_number' => 'LSE-SEC-001',
            'property_id' => $property->id,
            'unit_id' => $units[0]->id,
            'tenant_id' => $tenantA->id,
            'rent_model' => RentModel::FIXED,
            'fixed_rate_per_month' => 5_000_000,
            'service_charge_per_month' => 1_000_000,
            'deposit_amount' => 15_000_000,
            'start_date' => now()->startOfYear(),
            'end_date' => now()->addYear(),
            'status' => LeaseStatus::ACTIVE,
        ]);

        $leaseB = Lease::create([
            'lease_number' => 'LSE-SEC-002',
            'property_id' => $property->id,
            'unit_id' => $units[1]->id,
            'tenant_id' => $tenantB->id,
            'rent_model' => RentModel::FIXED,
            'fixed_rate_per_month' => 8_000_000,
            'service_charge_per_month' => 1_500_000,
            'deposit_amount' => 24_000_000,
            'start_date' => now()->startOfYear(),
            'end_date' => now()->addYear(),
            'status' => LeaseStatus::ACTIVE,
        ]);

        $invoiceA = Invoice::create([
            'property_id' => $property->id,
            'tenant_id' => $tenantA->id,
            'lease_id' => $leaseA->id,
            'invoice_number' => 'INV-MALL-SEC-001',
            'period_month' => '2026-10',
            'subtotal' => 5_000_000,
            'penalty_amount' => 0,
            'total_amount' => 5_000_000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::ISSUED,
            'due_date' => now()->addDays(7),
            'issued_at' => now(),
        ]);

        $invoiceB = Invoice::create([
            'property_id' => $property->id,
            'tenant_id' => $tenantB->id,
            'lease_id' => $leaseB->id,
            'invoice_number' => 'INV-MALL-SEC-002',
            'period_month' => '2026-10',
            'subtotal' => 8_000_000,
            'penalty_amount' => 0,
            'total_amount' => 8_000_000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::ISSUED,
            'due_date' => now()->addDays(7),
            'issued_at' => now(),
        ]);

        // Tenant A dapat melihat tagihannya sendiri
        $responseSelf = $this->actingAs($userA)->get(route('mall.portal.invoice', $invoiceA->id));
        $responseSelf->assertOk();
        $responseSelf->assertSee('INV-MALL-SEC-001');

        // Tenant A DILARANG melihat tagihan Tenant B (IDOR prevention)
        $responseIdor = $this->actingAs($userA)->get(route('mall.portal.invoice', $invoiceB->id));
        $this->assertEquals(403, $responseIdor->getStatusCode());

        // Tenant B DILARANG melihat tagihan Tenant A
        $responseIdorB = $this->actingAs($userB)->get(route('mall.portal.invoice', $invoiceA->id));
        $this->assertEquals(403, $responseIdorB->getStatusCode());
    }

    // ============================================================
    // 2. MASS ASSIGNMENT PROTECTION
    // ============================================================

    public function test_mass_assignment_protection_on_invoice_order_and_vehicle(): void
    {
        $property = Property::first();
        $tenant = Tenant::first();

        // 1. Invoice: attribute unfillable 'is_hacked' atau 'secret_override' tidak boleh tersimpan
        $invoice = new Invoice;
        $invoice->fill([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'invoice_number' => 'INV-MASS-01',
            'period_month' => '2026-10',
            'subtotal' => 1_000_000,
            'total_amount' => 1_000_000,
            'unfillable_override' => 'malicious_data',
        ]);
        $this->assertArrayNotHasKey('unfillable_override', $invoice->getAttributes());

        // 2. Vehicle: deleted_at dan unfillable attribute tidak boleh diisi via fillable
        $vehicle = new Vehicle;
        $vehicle->fill([
            'plate_number' => 'B 1337 HCK',
            'vin' => 'VIN-HACK-12345',
            'color' => 'Hitam',
            'deleted_at' => now()->toDateTimeString(),
            'is_admin' => true,
        ]);
        $this->assertArrayNotHasKey('deleted_at', $vehicle->getAttributes());
        $this->assertArrayNotHasKey('is_admin', $vehicle->getAttributes());

        // 3. Store Order: kolom berbahaya unfillable tidak boleh terisi
        $order = new StoreOrder;
        $order->fill([
            'number' => 'ORD-SEC-001',
            'unauthorized_bypass' => 1,
            'super_admin' => 1,
        ]);
        $this->assertArrayNotHasKey('unauthorized_bypass', $order->getAttributes());
        $this->assertArrayNotHasKey('super_admin', $order->getAttributes());
    }

    // ============================================================
    // 3. PIN BRUTE FORCE LOCKOUT (5 FAILED ATTEMPTS)
    // ============================================================

    public function test_pin_brute_force_lockout_after_five_failed_attempts(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $setPinAction = app(SetPinAction::class);
        $verifyPinAction = app(VerifyPinAction::class);

        // Pasang PIN awal
        $setPinAction->execute($user, '123456');
        $user->load('walletPin');

        $this->assertEquals(0, $user->walletPin->failed_attempts);
        $this->assertFalse($user->walletPin->isLocked());

        // 4 percobaan salah berturut-turut
        for ($i = 1; $i <= 4; $i++) {
            try {
                $verifyPinAction->execute($user, '000000');
                $this->fail("Percobaan ke-{$i} harus melempar InvalidPinException");
            } catch (InvalidPinException $e) {
                $user->walletPin->refresh();
                $this->assertEquals($i, $user->walletPin->failed_attempts);
                $this->assertFalse($user->walletPin->isLocked());
            }
        }

        // Percobaan ke-5 salah -> harus mengunci akun (PinLockedException)
        try {
            $verifyPinAction->execute($user, '000000');
            $this->fail('Percobaan ke-5 harus melempar PinLockedException dan mengunci akun');
        } catch (PinLockedException $e) {
            $user->walletPin->refresh();
            $this->assertEquals(5, $user->walletPin->failed_attempts);
            $this->assertTrue($user->walletPin->isLocked());
            $this->assertNotNull($user->walletPin->locked_until);
        }

        // Percobaan berikutnya meskipun PIN benar tetap DITOLAK karena akun terkunci
        $this->expectException(PinLockedException::class);
        $verifyPinAction->execute($user, '123456');
    }

    // ============================================================
    // 4. SIGNED URL VERIFICATION
    // ============================================================

    public function test_signed_url_verification_rejects_tampered_or_unsigned_requests(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => null,
        ]);

        $validSignedUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        // 1. Valid signed URL -> diproses dengan benar (redirect setelah verifikasi)
        $responseValid = $this->actingAs($user)->get($validSignedUrl);
        $this->assertTrue(
            in_array($responseValid->getStatusCode(), [200, 302], true),
            "Valid signed URL harus diterima (diterima: {$responseValid->getStatusCode()})"
        );

        // 2. Tampered URL (signature diubah) -> harus 403 Forbidden
        $tamperedUrl = $validSignedUrl.'&tamper=hack';
        $responseTampered = $this->actingAs($user)->get($tamperedUrl);
        $this->assertEquals(403, $responseTampered->getStatusCode());

        // 3. Unsigned URL (tanpa parameter signature) -> harus 403 Forbidden
        $unsignedUrl = route('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);
        $responseUnsigned = $this->actingAs($user)->get($unsignedUrl);
        $this->assertEquals(403, $responseUnsigned->getStatusCode());
    }

    // ============================================================
    // 5. XSS (CROSS-SITE SCRIPTING) PREVENTION
    // ============================================================

    public function test_xss_payload_is_properly_escaped_in_blade_views(): void
    {
        $property = Property::first();
        $user = User::factory()->create(['role' => 'tenant']);
        $xssBrand = 'XSS Brand <script>alert("XSS_PAYLOAD")</script>';

        $tenant = Tenant::create([
            'property_id' => $property->id,
            'user_id' => $user->id,
            'company_name' => 'PT XSS Security Indo',
            'brand_name' => $xssBrand,
            'category' => 'fnb',
            'pic_name' => 'Sec Tester',
            'pic_email' => 'sec@test.com',
            'pic_phone' => '08123456789',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('mall.portal.index'));
        $response->assertOk();

        // Script tag RAW tidak boleh dieksekusi / dirender mentah
        $response->assertDontSee($xssBrand, false);

        // Harus di-escape menjadi entitas HTML
        $response->assertSee('&lt;script&gt;alert(&quot;XSS_PAYLOAD&quot;)&lt;/script&gt;', false);
    }
}
