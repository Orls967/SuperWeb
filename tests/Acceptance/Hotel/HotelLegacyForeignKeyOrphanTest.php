<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\TimeshareUnit;
use RuntimeException;
use Tests\TestCase;

class HotelLegacyForeignKeyOrphanTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_throws_when_orphans_exist_and_report_orphans_command_lists_them(): void
    {
        $migrationPath = 'modules/Hotel/database/migrations/2026_10_29_195404_add_foreign_keys_to_legacy_htl_tables.php';

        // 1. Rollback migrasi FK terlebih dahulu
        Artisan::call('migrate:rollback', [
            '--path' => $migrationPath,
        ]);

        // 2. Buat data parent valid
        $propertyId = (string) Str::uuid();
        $property = HotelProperty::create([
            'id' => $propertyId,
            'property_code' => 'HTL-TEST-99',
            'name' => 'Test Hotel',
            'property_type' => 'HOTEL',
            'city' => 'Jakarta',
            'total_rooms' => 10,
        ]);

        $roomId = (string) Str::uuid();
        $room = HotelRoom::create([
            'id' => $roomId,
            'property_id' => $propertyId,
            'room_number' => '901',
            'room_type' => 'DELUXE',
            'base_rate_idr' => 500000,
            'status' => 'CLEAN_VACANT',
        ]);

        $folioId = (string) Str::uuid();
        $folio = HotelFolio::create([
            'id' => $folioId,
            'reservation_id' => (string) Str::uuid(),
            'guest_user_id' => (string) Str::uuid(),
            'total_room_charges' => 0,
            'total_addon_charges' => 0,
            'total_paid' => 0,
            'outstanding_balance' => 0,
            'status' => 'OPEN',
        ]);

        $timeshareUnitId = (string) Str::uuid();
        $timeshareUnit = TimeshareUnit::create([
            'id' => $timeshareUnitId,
            'property_id' => $propertyId,
            'unit_code' => 'TSU-99',
            'unit_name' => 'Presidential Suite',
            'total_token_shares' => 52,
            'daily_rental_rate_idr' => 1000000,
        ]);

        // 3. Masukkan record orphan yang melanggar FK
        $nonExistentFolio = (string) Str::uuid();
        $orphanFolioItemId = (string) Str::uuid();
        DB::table('htl_folio_items')->insert([
            'id' => $orphanFolioItemId,
            'folio_id' => $nonExistentFolio,
            'item_category' => 'MINIBAR',
            'description' => 'Orphan Minibar snack',
            'amount_idr' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $nonExistentProperty = (string) Str::uuid();
        $orphanResPropId = (string) Str::uuid();
        DB::table('htl_reservations')->insert([
            'id' => $orphanResPropId,
            'reservation_number' => 'RSV-ORPHAN-PROP',
            'property_id' => $nonExistentProperty,
            'room_id' => $roomId,
            'guest_user_id' => (string) Str::uuid(),
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'daily_rate_idr' => 500000,
            'total_room_charge_idr' => 1000000,
            'status' => 'CONFIRMED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $nonExistentRoom = (string) Str::uuid();
        $orphanResRoomId = (string) Str::uuid();
        DB::table('htl_reservations')->insert([
            'id' => $orphanResRoomId,
            'reservation_number' => 'RSV-ORPHAN-ROOM',
            'property_id' => $propertyId,
            'room_id' => $nonExistentRoom,
            'guest_user_id' => (string) Str::uuid(),
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'daily_rate_idr' => 500000,
            'total_room_charge_idr' => 1000000,
            'status' => 'CONFIRMED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $nonExistentTimeshareUnit = (string) Str::uuid();
        $orphanInvestorId = (string) Str::uuid();
        DB::table('htl_timeshare_investors')->insert([
            'id' => $orphanInvestorId,
            'timeshare_unit_id' => $nonExistentTimeshareUnit,
            'investor_user_id' => (string) Str::uuid(),
            'token_shares' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Verifikasi migrasi melempar RuntimeException memuat jumlah per kolom dan nama command
        $caughtException = null;
        try {
            Artisan::call('migrate', [
                '--path' => $migrationPath,
            ]);
        } catch (RuntimeException $e) {
            $caughtException = $e;
        }

        $this->assertNotNull($caughtException, 'Migrasi harus melempar exception saat terdapat baris orphan');
        $this->assertStringContainsString('htl_folio_items.folio_id: 1', $caughtException->getMessage());
        $this->assertStringContainsString('htl_reservations.property_id: 1', $caughtException->getMessage());
        $this->assertStringContainsString('htl_reservations.room_id: 1', $caughtException->getMessage());
        $this->assertStringContainsString('htl_timeshare_investors.timeshare_unit_id: 1', $caughtException->getMessage());
        $this->assertStringContainsString('hotel:report-orphans', $caughtException->getMessage());

        // 5. Verifikasi command hotel:report-orphans exit 0 dan mencetak baris orphan
        $exitCode = Artisan::call('hotel:report-orphans');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('htl_folio_items (missing folio_id)', $output);
        $this->assertStringContainsString('htl_reservations (missing property_id)', $output);
        $this->assertStringContainsString('htl_reservations (missing room_id)', $output);
        $this->assertStringContainsString('htl_timeshare_investors (missing timeshare_unit_id)', $output);
        $this->assertStringContainsString('RSV-ORPHAN-PROP', $output);
        $this->assertStringContainsString('RSV-ORPHAN-ROOM', $output);

        // 6. Bersihkan data orphan
        DB::table('htl_folio_items')->where('id', $orphanFolioItemId)->delete();
        DB::table('htl_reservations')->whereIn('id', [$orphanResPropId, $orphanResRoomId])->delete();
        DB::table('htl_timeshare_investors')->where('id', $orphanInvestorId)->delete();

        // 7. Setelah orphan dibersihkan, migrasi sukses tanpa error
        $secondExitCode = Artisan::call('migrate', [
            '--path' => $migrationPath,
        ]);
        $this->assertSame(0, $secondExitCode);

        // Command sekarang menampilkan 0 orphans
        Artisan::call('hotel:report-orphans');
        $cleanOutput = Artisan::output();
        $this->assertStringContainsString('No orphan records found', $cleanOutput);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
}
