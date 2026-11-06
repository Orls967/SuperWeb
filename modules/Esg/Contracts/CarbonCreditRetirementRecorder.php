<?php

declare(strict_types=1);

namespace Modules\Esg\Contracts;

/**
 * Kontrak lintas modul G7-K10: Pencatat pensiun kredit karbon & REC ke modul ESG.
 * Memastikan klaim dekarbonisasi diverifikasi dan dialokasikan ke profil emisi entitas.
 */
interface CarbonCreditRetirementRecorder
{
    /**
     * Catat resmi pensiun kredit karbon atau REC.
     *
     * @param  string  $vintageYear  Tahun vintage kredit (YYYY)
     * @param  int  $quantityKgCo2e  Kuantitas emisi yang diimbangi (kg CO2e)
     * @param  string  $serialNumber  Nomor seri sertifikat / batch unik
     * @param  string  $reason  Alasan klaim netralitas karbon / kepatuhan
     * @param  string  $entityPartyId  ID entitas konglomerat atau pelanggan pihak ketiga
     * @return string ID registri referensi pencatatan ESG
     */
    public function recordRetirement(
        string $vintageYear,
        int $quantityKgCo2e,
        string $serialNumber,
        string $reason,
        string $entityPartyId,
    ): string;
}
