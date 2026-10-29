<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Application\Actions\ExecuteHotelNightAuditAction;
use Modules\Hotel\Domain\Models\HotelProperty;

class HotelNightAuditCommand extends Command
{
    protected $signature = 'hotel:night-audit {property_id?}';

    protected $description = 'Menjalankan proses night audit untuk properti hotel';

    public function handle(ExecuteHotelNightAuditAction $action): int
    {
        $propertyId = $this->argument('property_id');

        $properties = $propertyId
            ? HotelProperty::where('id', $propertyId)->get()
            : HotelProperty::all();

        if ($properties->isEmpty()) {
            $this->error('Tidak ada properti hotel yang ditemukan.');

            return self::FAILURE;
        }

        foreach ($properties as $property) {
            $this->info("Menjalankan night audit untuk properti: {$property->name} ({$property->id})...");

            $snapshot = $action->execute($property->id);

            $this->table(
                ['Metrik', 'Nilai'],
                [
                    ['Business Date', $snapshot->business_date->toDateString()],
                    ['Total Kamar', $snapshot->total_rooms],
                    ['Kamar Terisi (Occupied)', $snapshot->occupied_rooms],
                    ['Out of Order', $snapshot->out_of_order_rooms],
                    ['Tingkat Hunian (Occupancy)', ($snapshot->occupancy_rate_bps / 100).'%'],
                    ['Pendapatan Kamar (IDR)', number_format($snapshot->total_room_revenue_idr, 0, ',', '.')],
                    ['Pendapatan F&B (IDR)', number_format($snapshot->total_fnb_revenue_idr, 0, ',', '.')],
                    ['ADR (IDR)', number_format($snapshot->adr_idr, 0, ',', '.')],
                    ['RevPAR (IDR)', number_format($snapshot->revpar_idr, 0, ',', '.')],
                ]
            );
        }

        $this->info('Night audit berhasil diselesaikan.');

        return self::SUCCESS;
    }
}
