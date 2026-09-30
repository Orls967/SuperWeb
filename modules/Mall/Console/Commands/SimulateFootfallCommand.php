<?php

declare(strict_types=1);

namespace Modules\Mall\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Mall\Application\Services\FootfallGenerator;
use Modules\Mall\Domain\Models\Property;

class SimulateFootfallCommand extends Command
{
    protected $signature = 'mall:simulate-footfall
                            {--days=30 : Jumlah hari ke belakang yang diisi}
                            {--property= : ID properti tertentu, default semua properti}
                            {--base=420 : Rata-rata kunjungan per jam sebelum bobot jam & hari}';

    protected $description = 'Bangkitkan data kunjungan (footfall) per gate per jam untuk analitik dan demo mall';

    public function handle(FootfallGenerator $generator): int
    {
        $days = max(1, (int) $this->option('days'));
        $base = max(1, (int) $this->option('base'));
        $propertyId = $this->option('property');

        $properties = Property::query()
            ->when($propertyId !== null, fn ($query) => $query->where('id', (int) $propertyId))
            ->get();

        if ($properties->isEmpty()) {
            $this->error('Tidak ada properti mall yang ditemukan. Jalankan seeder mall terlebih dahulu.');

            return self::FAILURE;
        }

        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays($days - 1);
        $totalRows = 0;

        foreach ($properties as $property) {
            $rows = $generator->generate($property, $startDate, $endDate, $base);
            $totalRows += $rows;

            $this->line("  {$property->name}: {$rows} baris jam-gate ditulis");
        }

        $this->info(sprintf(
            '✓ Data kunjungan %s s/d %s selesai dibuat (%d baris, %d gate per jam).',
            $startDate->format('d/m/Y'),
            $endDate->format('d/m/Y'),
            $totalRows,
            count(FootfallGenerator::GATES)
        ));

        return self::SUCCESS;
    }
}
