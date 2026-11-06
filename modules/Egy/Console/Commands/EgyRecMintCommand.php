<?php

declare(strict_types=1);

namespace Modules\Egy\Console\Commands;

use Illuminate\Console\Command;
use Modules\Egy\Application\Actions\MintRenewableEnergyCertificateAction;
use Modules\Egy\Domain\Models\GenerationAsset;

final class EgyRecMintCommand extends Command
{
    protected $signature = 'egy:rec-mint {--period= : Periode pembangkitan YYYY-MM} {--mwh=50 : Kuantitas MWh default jika belum tercatat}';

    protected $description = 'Cetak sertifikat atribut energi terbarukan (REC) unik per MWh listrik hijau yang dibangkitkan';

    public function handle(MintRenewableEnergyCertificateAction $action): int
    {
        $period = (string) ($this->option('period') ?: date('Y-m'));
        $vintageYear = substr($period, 0, 4);
        $defaultMwh = (int) $this->option('mwh');

        $this->info("Memindai aset pembangkit energi terbarukan untuk minting REC periode {$period}...");

        $assets = GenerationAsset::whereIn('fuel_type', ['SOLAR_PV', 'BIOGAS_WTE', 'HYDRO'])->get();
        $mintedCount = 0;

        foreach ($assets as $asset) {
            $energyWh = $defaultMwh * 1_000_000_000; // Wh

            $rec = $action->execute(
                generationAssetId: (int) $asset->id,
                sourceType: strtolower((string) $asset->fuel_type),
                periodMonth: $period,
                vintageYear: $vintageYear,
                energyWh: $energyWh,
                ownerPartyId: 1, // Developer holding
                initialTradingPriceMinor: 35_000
            );

            $this->info(sprintf('  [REC] Seri #%s (%d MWh) berhasil diterbitkan untuk aset [%s]', $rec->certificate_serial, $defaultMwh, $asset->name));
            $mintedCount++;
        }

        $this->info("Total REC berhasil dicetak: {$mintedCount} unit.");

        return self::SUCCESS;
    }
}
