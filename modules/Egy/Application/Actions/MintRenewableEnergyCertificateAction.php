<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use InvalidArgumentException;
use Modules\Egy\Domain\Models\RecCertificate;

/**
 * Mencetak unit sertifikat energi terbarukan (REC) unik per MWh listrik hijau yang dibangkitkan.
 */
final class MintRenewableEnergyCertificateAction
{
    /**
     * @param  int  $generationAssetId  ID aset pembangkit (Solar/Biogas)
     * @param  string  $sourceType  Jenis energi terbarukan
     * @param  string  $periodMonth  Periode bulan YYYY-MM
     * @param  string  $vintageYear  Tahun vintage YYYY
     * @param  int  $energyWh  Energi yang dibangkitkan dalam Watt-hours (Wh)
     * @param  int  $ownerPartyId  Pihak pengembang / pemilik awal sertifikat
     * @param  int|null  $initialTradingPriceMinor  Harga penawaran perdana (opsional)
     */
    public function execute(
        int $generationAssetId,
        string $sourceType,
        string $periodMonth,
        string $vintageYear,
        int $energyWh,
        int $ownerPartyId,
        ?int $initialTradingPriceMinor = null,
    ): RecCertificate {
        if ($energyWh <= 0) {
            throw new InvalidArgumentException('Kuantitas energi REC harus > 0 Wh');
        }

        $serialNumber = sprintf(
            'IREC-%s-%d-%s',
            strtoupper($sourceType),
            $generationAssetId,
            str_replace('-', '', $periodMonth)
        );

        // Idempoten: jika batch serial ini sudah pernah dicetak, kembalikan yang ada
        $existing = RecCertificate::where('certificate_serial', $serialNumber)->first();
        if ($existing !== null) {
            return $existing;
        }

        $rawSignature = sprintf(
            'REC-MINT|SER:%s|ASSET:%d|SRC:%s|PERIOD:%s|VINT:%s|WH:%d|OWNER:%d',
            $serialNumber,
            $generationAssetId,
            $sourceType,
            $periodMonth,
            $vintageYear,
            $energyWh,
            $ownerPartyId
        );
        $certificateHash = hash('sha256', $rawSignature);

        return RecCertificate::create([
            'certificate_serial' => $serialNumber,
            'generation_asset_id' => $generationAssetId,
            'energy_source_type' => $sourceType,
            'period_month' => $periodMonth,
            'vintage_year' => $vintageYear,
            'energy_wh' => $energyWh,
            'owner_party_id' => $ownerPartyId,
            'status' => 'minted',
            'trading_price_minor' => $initialTradingPriceMinor ?? 35_000, // Rp 35.000 / MWh default
            'certificate_hash' => $certificateHash,
        ]);
    }
}
