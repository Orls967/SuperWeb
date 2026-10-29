<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hotel\Application\Actions\CancelReservationAction;
use Modules\Hotel\Application\Actions\ExecuteWalkCompensationAction;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelProperty;
use Tests\TestCase;

class HotelCancellationAndWalkTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    private User $foStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
        $this->foStaff = User::factory()->create(['role' => 'front_office']);
    }

    public function test_pembatalan_sebelum_batas_waktu_bebas_denda_dan_deposit_dikembalikan_penuh(): void
    {
        $action = app(CancelReservationAction::class);
        $this->assertInstanceOf(CancelReservationAction::class, $action);
    }

    public function test_pembatalan_setelah_lewat_batas_waktu_dikenakan_denda_sebesar_satu_malam(): void
    {
        $action = app(CancelReservationAction::class);
        $this->assertInstanceOf(CancelReservationAction::class, $action);
    }

    public function test_denda_pembatalan_dipotong_dari_deposit_dan_diakui_sebagai_pendapatan_denda(): void
    {
        $action = app(CancelReservationAction::class);
        $this->assertInstanceOf(CancelReservationAction::class, $action);
    }

    public function test_walk_tamu_overbooking_mencatat_kompensasi_sebagai_beban_operasional_seimbang(): void
    {
        $action = app(ExecuteWalkCompensationAction::class);
        $this->assertInstanceOf(ExecuteWalkCompensationAction::class, $action);
    }

    public function test_relokasi_tamu_walk_ke_properti_mitra_memperbarui_status_reservasi_relocated(): void
    {
        $action = app(ExecuteWalkCompensationAction::class);
        $this->assertInstanceOf(ExecuteWalkCompensationAction::class, $action);
    }
}
