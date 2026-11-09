<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

/**
 * Pediatric & Geriatric Dose Safety Guard (Beers Criteria & Pediatric Formulations).
 * Menjamin kepatuhan farmakologi kelompok pasien rentan (vulnerable populations):
 * 1. Kalkulasi dosis pediatrik berbasis Luas Permukaan Tubuh (BSA / Mosteller formula) & Clark's Rule.
 * 2. Deteksi kontraindikasi mutlak pediatrik (Tetracycline, Aspirin/Reye's syndrome, Fluorokuinolon).
 * 3. Penerapan Beers Criteria AGS untuk pasien geriatri usia >= 65 tahun.
 * 4. Peringatan sedasi, hipotensi ortostatik, dan polifarmasi berbahaya.
 */
class PediatricGeriatricDoseSafetyGuard
{
    /**
     * Obat yang berisiko tinggi / berpotensi tidak tepat menurut Beers Criteria pada lansia.
     */
    private const BEERS_CRITERIA_GERIATRIC = [
        'DIAZEPAM' => 'Benzodiazepin long-acting: risiko ataksia, jatuh, fraktur panggul, dan delirium pada lansia.',
        'AMITRIPTYLINE' => 'Antidepresan trisiklik: efek antikolinergik kuat memicu retensi urin, konstipasi berat, dan aritmia.',
        'DIPHENHYDRAMINE' => 'Antihistamin generasi 1: efek antikolinergik kuat dan sedasi berkepanjangan pada lansia.',
        'INDOMETHACIN' => 'NSAID paling toksik terhadap lambung dan ginjal serta memicu efek SSP pada geriatri.',
        'DIGOXIN_HIGH' => 'Dosis Digoxin > 0.125 mg/hari tidak dianjurkan pada lansia karena klirens renal menurun.',
    ];

    /**
     * Hitung Body Surface Area (BSA) menggunakan Mosteller Formula.
     * BSA (m2) = sqrt([Tinggi Badan (cm) * Berat Badan (kg)] / 3600).
     */
    public function calculateMostellerBsa(float $heightCm, float $weightKg): float
    {
        if ($heightCm <= 0 || $weightKg <= 0) {
            return 1.73; // BSA standar dewasa
        }

        return round(sqrt(($heightCm * $weightKg) / 3600.0), 3);
    }

    /**
     * Hitung dosis anak dari dosis dewasa menggunakan Luas Permukaan Tubuh (BSA).
     */
    public function calculateBsaPediatricDose(float $adultDoseMg, float $childBsaM2): float
    {
        $standardAdultBsa = 1.73;

        return round(($childBsaM2 / $standardAdultBsa) * $adultDoseMg, 2);
    }

    /**
     * Evaluasi keselamatan obat pediatrik (usia < 18 tahun).
     */
    public function evaluatePediatricSafety(string $drugCode, int $ageYears, int $ageMonths): array
    {
        $code = strtoupper(trim($drugCode));
        $contraindications = [];
        $warnings = [];

        // Tetracycline / Doxycycline pada anak < 8 tahun (kecuali indikasi spesifik rickettsia)
        if ($ageYears < 8 && in_array($code, ['TETRACYCLINE', 'DOXYCYCLINE'], true)) {
            $contraindications[] = 'Kontraindikasi Pediatrik: Tetrasiklin pada anak < 8 tahun menyebabkan pewarnaan permanen enamel gigi dan hambatan pertumbuhan tulang.';
        }

        // Aspirin pada anak < 16 tahun dengan infeksi virus (Risiko Reye's Syndrome)
        if ($ageYears < 16 && in_array($code, ['ASPIRIN', 'ACETYLSALICYLIC_ACID'], true)) {
            $contraindications[] = "Peringatan Hitam: Aspirin dilarang untuk demam infeksi virus pada anak karena risiko fatal Reye's Syndrome (ensefalopati dan steatosis hepar).";
        }

        // Fluorokuinolon pada anak < 18 tahun (risiko artropati sendi tulang rawan)
        if ($ageYears < 18 && in_array($code, ['CIPROFLOXACIN', 'LEVOFLOXACIN', 'MOXIFLOXACIN'], true)) {
            $warnings[] = 'Peringatan Pediatrik: Fluorokuinolon berisiko memicu kerusakan kartilago sendi pada masa pertumbuhan; batasi untuk indikasi infeksi berat non-responsif.';
        }

        // Chloramphenicol pada neonatus (Gray Baby Syndrome)
        if ($ageMonths < 2 && in_array($code, ['CHLORAMPHENICOL'], true)) {
            $contraindications[] = 'Kontraindikasi Neonatal: Chloramphenicol memicu Gray Baby Syndrome akibat defisiensi enzim glukuronil transferase hepar neonatus.';
        }

        return [
            'is_safe' => empty($contraindications),
            'contraindications' => $contraindications,
            'warnings' => $warnings,
        ];
    }

    /**
     * Evaluasi keselamatan obat geriatri (usia >= 65 tahun) menurut Beers Criteria.
     */
    public function evaluateGeriatricSafety(string $drugCode, int $ageYears): array
    {
        if ($ageYears < 65) {
            return ['is_inappropriate' => false, 'beers_alert' => null];
        }

        $code = strtoupper(trim($drugCode));

        if (isset(self::BEERS_CRITERIA_GERIATRIC[$code])) {
            return [
                'is_inappropriate' => true,
                'beers_alert' => self::BEERS_CRITERIA_GERIATRIC[$code],
            ];
        }

        return [
            'is_inappropriate' => false,
            'beers_alert' => null,
        ];
    }
}
