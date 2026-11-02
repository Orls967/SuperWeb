<?php

declare(strict_types=1);

namespace Modules\Hotel\tests\Unit;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRatePlan;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomType;

trait HotelUnitTestHelper
{
    protected HotelProperty $property;

    protected User $guest;

    protected User $staff;

    protected HotelRoomType $roomType;

    protected HotelRatePlan $barPlan;

    protected HotelRoom $room;

    protected function setupHotelFixture(): void
    {
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
        $this->guest = User::factory()->create(['role' => 'guest']);
        $this->staff = User::factory()->create(['role' => 'front_office']);
        $this->roomType = HotelRoomType::where('property_id', $this->property->id)->firstOrFail();
        $this->barPlan = HotelRatePlan::where('property_id', $this->property->id)->where('plan_type', 'BAR')->firstOrFail();
        $this->room = HotelRoom::where('property_id', $this->property->id)->firstOrFail();
    }
}
