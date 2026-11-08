<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Application\Actions\ReleaseExpiredRoomBlockAllotmentsAction;

final class HotelReleaseRoomBlockAllotmentsCommand extends Command
{
    protected $signature = 'hotel:release-room-blocks {--property= : Filter properti spesifik} {--dry-run : Simulasi tanpa simpan}';

    protected $description = 'Melepaskan kuota kamar blok MICE/Wedding bertingkat (H-30, H-14, H-7) kembali ke inventori publik';

    public function handle(ReleaseExpiredRoomBlockAllotmentsAction $action): int
    {
        $propertyId = $this->option('property');
        $this->info('Menjalankan pelepasan kamar blok MICE... Properti: '.($propertyId ?: 'ALL'));

        $certs = $action->execute($propertyId);

        $this->info('Pelepasan selesai. Total blok terproses yang melepaskan kuota: '.count($certs));

        foreach ($certs as $cert) {
            $this->line(" - Blok: {$cert->blockId} | Cutoff: {$cert->cutoffTier} | Dilepas: {$cert->roomsReleasedToPublic} kamar | Sisa: {$cert->remainingContractedRooms} kamar");
        }

        return self::SUCCESS;
    }
}
