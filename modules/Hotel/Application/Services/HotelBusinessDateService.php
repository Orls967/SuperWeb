<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Modules\Core\Contracts\ClockInterface;
use Modules\Hotel\Domain\Models\HotelBusinessDate;

class HotelBusinessDateService
{
    public function __construct(
        protected ?ClockInterface $clock = null
    ) {}

    public function getCurrentBusinessDate(string $propertyId): string
    {
        $record = HotelBusinessDate::where('property_id', $propertyId)->first();

        if ($record) {
            return Carbon::parse($record->current_business_date)->toDateString();
        }

        // Jika belum diinisialisasi, inisialisasi dari ClockInterface atau hari ini
        $now = $this->clock ? $this->clock->now() : Carbon::now();
        $dateStr = Carbon::parse($now)->toDateString();

        HotelBusinessDate::create([
            'id' => (string) Str::uuid(),
            'property_id' => $propertyId,
            'current_business_date' => $dateStr,
            'status' => 'open',
            'last_checkpoint' => null,
            'audited_at' => null,
        ]);

        return $dateStr;
    }

    public function getBusinessDateRecord(string $propertyId): HotelBusinessDate
    {
        $this->getCurrentBusinessDate($propertyId);

        return HotelBusinessDate::where('property_id', $propertyId)->firstOrFail();
    }
}
