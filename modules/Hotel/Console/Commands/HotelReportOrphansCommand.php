<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HotelReportOrphansCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hotel:report-orphans';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Report orphan records violating hotel foreign key relationships';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Scanning hotel tables for orphan records violating foreign keys...');

        $hasOrphans = false;

        // 1. htl_folio_items.folio_id -> htl_folios.id
        $folioItems = DB::table('htl_folio_items')
            ->whereNotNull('folio_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('htl_folios')
                    ->whereColumn('htl_folios.id', 'htl_folio_items.folio_id');
            })
            ->get(['id', 'folio_id', 'description', 'amount_idr']);

        if ($folioItems->isNotEmpty()) {
            $hasOrphans = true;
            $this->warn(sprintf('Found %d orphan rows in htl_folio_items (missing folio_id):', $folioItems->count()));
            $this->table(
                ['ID', 'Orphan Folio ID', 'Description', 'Amount IDR'],
                $folioItems->map(fn ($row) => [(string) $row->id, (string) $row->folio_id, (string) ($row->description ?? '-'), (string) ($row->amount_idr ?? 0)])->toArray()
            );
        } else {
            $this->line('✓ htl_folio_items.folio_id: 0 orphans');
        }

        // 2. htl_reservations.property_id -> htl_properties.id
        $resProperties = DB::table('htl_reservations')
            ->whereNotNull('property_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('htl_properties')
                    ->whereColumn('htl_properties.id', 'htl_reservations.property_id');
            })
            ->get(['id', 'reservation_number', 'property_id', 'status']);

        if ($resProperties->isNotEmpty()) {
            $hasOrphans = true;
            $this->warn(sprintf('Found %d orphan rows in htl_reservations (missing property_id):', $resProperties->count()));
            $this->table(
                ['ID', 'Reservation Number', 'Orphan Property ID', 'Status'],
                $resProperties->map(fn ($row) => [(string) $row->id, (string) ($row->reservation_number ?? '-'), (string) $row->property_id, (string) ($row->status ?? '-')])->toArray()
            );
        } else {
            $this->line('✓ htl_reservations.property_id: 0 orphans');
        }

        // 3. htl_reservations.room_id -> htl_rooms.id
        $resRooms = DB::table('htl_reservations')
            ->whereNotNull('room_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('htl_rooms')
                    ->whereColumn('htl_rooms.id', 'htl_reservations.room_id');
            })
            ->get(['id', 'reservation_number', 'room_id', 'status']);

        if ($resRooms->isNotEmpty()) {
            $hasOrphans = true;
            $this->warn(sprintf('Found %d orphan rows in htl_reservations (missing room_id):', $resRooms->count()));
            $this->table(
                ['ID', 'Reservation Number', 'Orphan Room ID', 'Status'],
                $resRooms->map(fn ($row) => [(string) $row->id, (string) ($row->reservation_number ?? '-'), (string) $row->room_id, (string) ($row->status ?? '-')])->toArray()
            );
        } else {
            $this->line('✓ htl_reservations.room_id: 0 orphans');
        }

        // 4. htl_timeshare_investors.timeshare_unit_id -> htl_timeshare_units.id
        $investors = DB::table('htl_timeshare_investors')
            ->whereNotNull('timeshare_unit_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('htl_timeshare_units')
                    ->whereColumn('htl_timeshare_units.id', 'htl_timeshare_investors.timeshare_unit_id');
            })
            ->get(['id', 'timeshare_unit_id', 'investor_user_id', 'token_shares']);

        if ($investors->isNotEmpty()) {
            $hasOrphans = true;
            $this->warn(sprintf('Found %d orphan rows in htl_timeshare_investors (missing timeshare_unit_id):', $investors->count()));
            $this->table(
                ['ID', 'Investor User ID', 'Orphan Timeshare Unit ID', 'Token Shares'],
                $investors->map(fn ($row) => [(string) $row->id, (string) ($row->investor_user_id ?? '-'), (string) $row->timeshare_unit_id, (string) ($row->token_shares ?? 0)])->toArray()
            );
        } else {
            $this->line('✓ htl_timeshare_investors.timeshare_unit_id: 0 orphans');
        }

        if (! $hasOrphans) {
            $this->info('No orphan records found across all checked hotel tables.');
        }

        return self::SUCCESS;
    }
}
