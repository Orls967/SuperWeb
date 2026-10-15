<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\AutoServe\Domain\Models\Service;
use Modules\Crypto\Domain\Enums\AlertCondition;
use Modules\Crypto\Domain\Models\CryptoAlert;
use Modules\Crypto\Domain\Models\CryptoAsset;
use Modules\Distribution\Domain\Models\Distributor;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Order;
use Tests\TestCase;

class CustomerResourceOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_b_cannot_view_customer_a_booking(): void
    {
        $customerA = User::factory()->create(['role' => 'customer']);
        $customerB = User::factory()->create(['role' => 'customer']);

        $service = Service::create([
            'name' => 'Ganti Oli Mesin',
            'slug' => 'ganti-oli-mesin',
            'price' => 150000,
            'is_active' => true,
        ]);

        $booking = Booking::create([
            'booking_code' => 'BOOK-OWN-001',
            'customer_id' => $customerA->id,
            'service_id' => $service->id,
            'plate_number' => 'B 1234 A',
            'vehicle_brand' => 'Toyota',
            'complaint' => 'Servis berkala',
            'booking_date' => now()->toDateString(),
            'status' => 'pending',
            'service_cost' => 150000,
            'sparepart_cost' => 0,
            'grand_total' => 150000,
        ]);

        // Customer A can view own booking
        $resA = $this->actingAs($customerA)->get(route('bookings.show', $booking));
        $this->assertSame(200, $resA->status());

        // Customer B must receive 403 Forbidden
        $resB = $this->actingAs($customerB)->get(route('bookings.show', $booking));
        $this->assertSame(403, $resB->status(), 'Customer B dilarang mengakses booking milik Customer A (harus 403).');
    }

    public function test_customer_b_cannot_view_customer_a_store_order(): void
    {
        $customerA = User::factory()->create(['role' => 'customer']);
        $customerB = User::factory()->create(['role' => 'customer']);

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $customerA->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'subtotal' => 200000,
            'shipping_fee' => 10000,
            'grand_total' => 210000,
        ]);

        // Customer A can view own order
        $resA = $this->actingAs($customerA)->get(route('store.orders.show', $order));
        $this->assertSame(200, $resA->status());

        // Customer B must receive 403 Forbidden
        $resB = $this->actingAs($customerB)->get(route('store.orders.show', $order));
        $this->assertSame(403, $resB->status(), 'Customer B dilarang mengakses order milik Customer A (harus 403).');
    }

    public function test_customer_b_cannot_delete_customer_a_crypto_alert(): void
    {
        $customerA = User::factory()->create(['role' => 'customer']);
        $customerB = User::factory()->create(['role' => 'customer']);

        $asset = CryptoAsset::create([
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'decimals' => 8,
            'is_active' => true,
        ]);

        $alert = CryptoAlert::create([
            'user_id' => $customerA->id,
            'asset_id' => $asset->id,
            'condition' => AlertCondition::ABOVE,
            'target_price_idr' => '1000000000',
            'is_triggered' => false,
        ]);

        // Customer B must receive 403 Forbidden
        $resB = $this->actingAs($customerB)->delete(route('crypto.alerts.destroy', $alert));
        $this->assertSame(403, $resB->status(), 'Customer B dilarang menghapus price alert milik Customer A (harus 403).');

        // Customer A can delete own alert
        $resA = $this->actingAs($customerA)->delete(route('crypto.alerts.destroy', $alert));
        $this->assertSame(302, $resA->status());
        $this->assertDatabaseMissing('crypto_alerts', ['id' => $alert->id]);
    }

    public function test_distribution_portal_admin_without_param_shows_selector(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Distributor::create([
            'code' => 'DST-001',
            'name' => 'Distributor A',
            'tier' => 'gold',
            'status' => 'approved',
            'credit_limit_idr' => 50000000,
            'payment_terms_days' => 30,
        ]);

        $res = $this->actingAs($admin)->get(route('distribution.portal.home'));
        $this->assertSame(200, $res->status());
        $res->assertViewIs('distribution::selector');
    }

    public function test_distribution_portal_distributor_param_by_non_admin_forbids_403(): void
    {
        $distUser = User::factory()->create(['role' => 'distributor']);

        $distA = Distributor::create([
            'code' => 'DST-A',
            'name' => 'Distributor A',
            'owner_user_id' => $distUser->id,
            'tier' => 'gold',
            'status' => 'approved',
            'credit_limit_idr' => 50000000,
            'payment_terms_days' => 30,
        ]);

        $distB = Distributor::create([
            'code' => 'DST-B',
            'name' => 'Distributor B',
            'tier' => 'silver',
            'status' => 'approved',
            'credit_limit_idr' => 20000000,
            'payment_terms_days' => 30,
        ]);

        $res = $this->actingAs($distUser)->get(route('distribution.portal.home', ['distributor' => $distB->id]));
        $this->assertSame(403, $res->status(), 'User distributor memanipulasi ?distributor=X harus 403.');
    }

    public function test_distribution_portal_admin_access_records_audit_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $dist = Distributor::create([
            'code' => 'DST-AUDIT-1',
            'name' => 'Distributor Audit',
            'tier' => 'gold',
            'status' => 'approved',
            'credit_limit_idr' => 50000000,
            'payment_terms_days' => 30,
        ]);

        $res = $this->actingAs($admin)->get(route('distribution.portal.home', ['distributor' => $dist->id]));
        $this->assertSame(200, $res->status());
        $res->assertViewIs('distribution::portal');

        $this->assertDatabaseHas('core_audit_logs', [
            'action' => 'distribution.portal.admin_view',
            'auditable_type' => get_class($dist),
            'auditable_id' => $dist->id,
        ]);
    }

    public function test_storage_local_upload_without_valid_signature_aborts_403(): void
    {
        $res = $this->put('/storage/unauthorized-file.txt');
        $this->assertSame(403, $res->status(), 'storage.local.upload tanpa signature harus 403.');
    }
}
