<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Application\Actions\PublishRatePlanAction;

final class HotelRatePublishCommand extends Command
{
    protected $signature = 'hotel:rate-publish {property : ID Properti} {from : Tanggal Mulai Y-m-d} {to : Tanggal Akhir Y-m-d}';

    protected $description = 'Menerbitkan tarif BAR harian hasil perhitungan H2 secara idempoten';

    public function handle(PublishRatePlanAction $action): int
    {
        $propertyId = (int) $this->argument('property');
        $from = (string) $this->argument('from');
        $to = (string) $this->argument('to');

        $res = $action->execute($propertyId, $from, $to);

        $this->info("Berhasil mempublikasikan {$res['published_records']} entri tarif BAR. Versi: {$res['version_code']}");

        return self::SUCCESS;
    }
}
