<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use InvalidArgumentException;

/**
 * Pelacak Biakan Mikrobiologi & Uji Kepekaan Antibiotik (Antibiogram AST) (Fase 105).
 *
 * Menerapkan standar CLSI M100 / EUCAST untuk pengujian kepekaan antimikroba:
 * - Interpretasi Minimum Inhibitory Concentration (MIC) & Zona Hambat Disk Diffusion (Kirby-Bauer)
 * - Kategori: Susceptible (S), Intermediate (I), Susceptible-Increased Exposure (I), Resistant (R)
 * - Deteksi fenotipik Multi-Drug Resistant Organism (MDRO):
 *   * MRSA (Methicillin-Resistant Staphylococcus aureus) via Cefoxitin disk/MIC
 *   * VRE (Vancomycin-Resistant Enterococcus faecium/faecalis)
 *   * ESBL (Extended-Spectrum Beta-Lactamase) pada Enterobacterales (Ceftriaxone/Ceftazidime resistant)
 *   * CRE (Carbapenem-Resistant Enterobacteriaceae) via Meropenem/Imipenem
 *   * XDR/PDR Pseudomonas aeruginosa & Acinetobacter baumannii
 * - Kompilasi matriks antibiogram rumah sakit untuk antimicrobial stewardship program.
 */
class MicrobiologyCultureAndSusceptibilityTracker
{
    /**
     * Referensi Breakpoint Interpretasi CLSI/EUCAST standar untuk MIC (ug/mL).
     *
     * @var array<string, array<string, array{s_max: float, r_min: float}>>
     */
    private const MIC_BREAKPOINTS = [
        'STAPHYLOCOCCUS_AUREUS' => [
            'OXACILLIN' => ['s_max' => 2.0, 'r_min' => 4.0],
            'VANCOMYCIN' => ['s_max' => 2.0, 'r_min' => 4.0],
            'LINEZOLID' => ['s_max' => 4.0, 'r_min' => 8.0],
            'DAPTOMYCIN' => ['s_max' => 1.0, 'r_min' => 2.0],
            'CEFOXITIN' => ['s_max' => 4.0, 'r_min' => 8.0],
            'TRIMETHOPRIM_SULFAMETHOXAZOLE' => ['s_max' => 2.0, 'r_min' => 4.0],
            'CLINDAMYCIN' => ['s_max' => 0.5, 'r_min' => 4.0],
        ],
        'ESCHERICHIA_COLI' => [
            'AMPICILLIN' => ['s_max' => 8.0, 'r_min' => 32.0],
            'CEFTRIAXONE' => ['s_max' => 1.0, 'r_min' => 4.0],
            'CEFTAZIDIME' => ['s_max' => 4.0, 'r_min' => 16.0],
            'MEROPENEM' => ['s_max' => 1.0, 'r_min' => 4.0],
            'IMIPENEM' => ['s_max' => 1.0, 'r_min' => 4.0],
            'CIPROFLOXACIN' => ['s_max' => 0.25, 'r_min' => 1.0],
            'AMIKACIN' => ['s_max' => 16.0, 'r_min' => 64.0],
            'GENTAMICIN' => ['s_max' => 4.0, 'r_min' => 16.0],
            'COLISTIN' => ['s_max' => 2.0, 'r_min' => 4.0],
        ],
        'KLEBSIELLA_PNEUMONIAE' => [
            'CEFTRIAXONE' => ['s_max' => 1.0, 'r_min' => 4.0],
            'CEFTAZIDIME' => ['s_max' => 4.0, 'r_min' => 16.0],
            'MEROPENEM' => ['s_max' => 1.0, 'r_min' => 4.0],
            'IMIPENEM' => ['s_max' => 1.0, 'r_min' => 4.0],
            'AMIKACIN' => ['s_max' => 16.0, 'r_min' => 64.0],
            'CIPROFLOXACIN' => ['s_max' => 0.25, 'r_min' => 1.0],
            'COLISTIN' => ['s_max' => 2.0, 'r_min' => 4.0],
        ],
        'PSEUDOMONAS_AERUGINOSA' => [
            'PIPERACILLIN_TAZOBACTAM' => ['s_max' => 16.0, 'r_min' => 128.0],
            'CEFTAZIDIME' => ['s_max' => 8.0, 'r_min' => 32.0],
            'CEFEPIME' => ['s_max' => 8.0, 'r_min' => 32.0],
            'MEROPENEM' => ['s_max' => 2.0, 'r_min' => 8.0],
            'CIPROFLOXACIN' => ['s_max' => 0.5, 'r_min' => 2.0],
            'AMIKACIN' => ['s_max' => 16.0, 'r_min' => 64.0],
            'COLISTIN' => ['s_max' => 2.0, 'r_min' => 4.0],
        ],
        'ENTEROCOCCUS_FAECIUM' => [
            'AMPICILLIN' => ['s_max' => 8.0, 'r_min' => 16.0],
            'VANCOMYCIN' => ['s_max' => 4.0, 'r_min' => 32.0],
            'LINEZOLID' => ['s_max' => 2.0, 'r_min' => 8.0],
            'DAPTOMYCIN' => ['s_max' => 4.0, 'r_min' => 8.0],
        ],
    ];

    /**
     * Interpretasikan kepekaan antibiotik tunggal berdasarkan nilai MIC.
     *
     * @param  string  $organism  Organisme patogen (misal: 'STAPHYLOCOCCUS_AUREUS')
     * @param  string  $antibiotic  Nama antibiotik (misal: 'VANCOMYCIN')
     * @param  float  $measuredMic  Nilai MIC dalam ug/mL
     * @return array{
     *     organism: string,
     *     antibiotic: string,
     *     mic_ug_ml: float,
     *     interpretation: string,
     *     breakpoint_s_max: float|null,
     *     breakpoint_r_min: float|null
     * }
     */
    public function interpretMic(string $organism, string $antibiotic, float $measuredMic): array
    {
        $orgKey = strtoupper(str_replace(' ', '_', trim($organism)));
        $abxKey = strtoupper(str_replace([' ', '-'], '_', trim($antibiotic)));

        if ($measuredMic <= 0.0) {
            throw new InvalidArgumentException('Nilai MIC harus lebih besar dari 0.');
        }

        $breakpoints = self::MIC_BREAKPOINTS[$orgKey][$abxKey] ?? null;

        if (! $breakpoints) {
            return [
                'organism' => $orgKey,
                'antibiotic' => $abxKey,
                'mic_ug_ml' => $measuredMic,
                'interpretation' => 'UNKNOWN_BREAKPOINT',
                'breakpoint_s_max' => null,
                'breakpoint_r_min' => null,
            ];
        }

        $sMax = $breakpoints['s_max'];
        $rMin = $breakpoints['r_min'];

        $interp = 'INTERMEDIATE';
        if ($measuredMic <= $sMax) {
            $interp = 'SUSCEPTIBLE';
        } elseif ($measuredMic >= $rMin) {
            $interp = 'RESISTANT';
        }

        return [
            'organism' => $orgKey,
            'antibiotic' => $abxKey,
            'mic_ug_ml' => $measuredMic,
            'interpretation' => $interp,
            'breakpoint_s_max' => $sMax,
            'breakpoint_r_min' => $rMin,
        ];
    }

    /**
     * Evaluasi panel lengkap hasil biakan dan uji resistensi multidrug (MDRO).
     *
     * @param  string  $specimenId  ID atau barcode spesimen
     * @param  string  $organism  Nama taksonomis bakteri patogen
     * @param  array<string, float>  $antibioticMicMap  Peta nama antibiotik => nilai MIC terukur
     * @param  string|null  $gramStain  Karakteristik pewarnaan Gram ('GRAM_POSITIVE_COCCI', 'GRAM_NEGATIVE_BACILLI', etc.)
     * @return array{
     *     specimen_id: string,
     *     organism: string,
     *     gram_stain: string|null,
     *     panel_results: list<array{antibiotic: string, mic: float, interpretation: string}>,
     *     mdro_classifications: list<string>,
     *     is_alert_organism: bool,
     *     infection_control_precautions: list<string>,
     *     recommended_regimens: list<string>
     * }
     */
    public function evaluateCulturePanel(
        string $specimenId,
        string $organism,
        array $antibioticMicMap,
        ?string $gramStain = null
    ): array {
        $orgKey = strtoupper(str_replace(' ', '_', trim($organism)));
        $panelResults = [];
        $resistantCount = 0;
        $testedCount = 0;

        $resultsByAbx = [];

        foreach ($antibioticMicMap as $abx => $mic) {
            $eval = $this->interpretMic($orgKey, $abx, (float) $mic);
            $panelResults[] = [
                'antibiotic' => $eval['antibiotic'],
                'mic' => $eval['mic_ug_ml'],
                'interpretation' => $eval['interpretation'],
            ];
            $resultsByAbx[$eval['antibiotic']] = $eval['interpretation'];

            $testedCount++;
            if ($eval['interpretation'] === 'RESISTANT') {
                $resistantCount++;
            }
        }

        // Deteksi Pola Resistensi Khusus (MDRO)
        $mdroList = [];
        $precautions = [];
        $recommendedRegimens = [];

        // 1. MRSA: Staphylococcus aureus resisten Oxacillin / Cefoxitin
        if ($orgKey === 'STAPHYLOCOCCUS_AUREUS') {
            $isOxacillinResistant = ($resultsByAbx['OXACILLIN'] ?? '') === 'RESISTANT';
            $isCefoxitinResistant = ($resultsByAbx['CEFOXITIN'] ?? '') === 'RESISTANT';

            if ($isOxacillinResistant || $isCefoxitinResistant) {
                $mdroList[] = 'MRSA (Methicillin-Resistant Staphylococcus aureus)';
                $precautions[] = 'Kewaspadaan Kontak (Contact Precautions), ruang isolasi cohorting/tunggal.';
                $recommendedRegimens[] = 'Lini pertama: Vancomycin IV (target trough 15-20 ug/mL) atau Linezolid 600mg q12h atau Daptomycin.';
            }

            // VRSA / VISA
            $vancMic = $antibioticMicMap['VANCOMYCIN'] ?? null;
            if ($vancMic !== null && $vancMic >= 4.0) {
                $mdroList[] = 'VISA/VRSA (Vancomycin-Intermediate/Resistant S. aureus)';
                $precautions[] = 'ISOLASI KETAT: Segera laporkan ke Komite PPI & Kepala Laboratorium Mikrobiologi.';
                $recommendedRegimens[] = 'Kombinasi Ceftaroline + Daptomycin, atau Quinupristin-Dalfopristin.';
            }
        }

        // 2. VRE: Enterococcus faecium/faecalis resisten Vancomycin
        if (str_contains($orgKey, 'ENTEROCOCCUS')) {
            if (($resultsByAbx['VANCOMYCIN'] ?? '') === 'RESISTANT') {
                $mdroList[] = 'VRE (Vancomycin-Resistant Enterococcus)';
                $precautions[] = 'Kewaspadaan Kontak, desinfeksi ketat peralatan medis dengan sporicidal agent.';
                $recommendedRegimens[] = 'Linezolid 600mg IV/PO q12h atau Daptomycin dosis tinggi (8-10 mg/kg/hari).';
            }
        }

        // 3. ESBL & CRE pada Enterobacterales (E. coli, K. pneumoniae)
        if (in_array($orgKey, ['ESCHERICHIA_COLI', 'KLEBSIELLA_PNEUMONIAE'], true)) {
            $isCeftriaxoneR = ($resultsByAbx['CEFTRIAXONE'] ?? '') === 'RESISTANT';
            $isCeftazidimeR = ($resultsByAbx['CEFTAZIDIME'] ?? '') === 'RESISTANT';
            $isMeroR = ($resultsByAbx['MEROPENEM'] ?? '') === 'RESISTANT';
            $isImiR = ($resultsByAbx['IMIPENEM'] ?? '') === 'RESISTANT';

            if ($isMeroR || $isImiR) {
                $mdroList[] = 'CRE (Carbapenem-Resistant Enterobacteriaceae)';
                $precautions[] = 'ALERTA TINGGI: Pasien harus diisolasi kontak di ruang rawat bertekanan netral/negatif.';
                $recommendedRegimens[] = 'Ceftazidime-Avibactam, Meropenem-Vaborbactam, atau kombinasi Polimiksin B/Colistin + Tigecycline.';
            } elseif ($isCeftriaxoneR || $isCeftazidimeR) {
                $mdroList[] = 'ESBL (Extended-Spectrum Beta-Lactamase Producer)';
                $precautions[] = 'Kewaspadaan Kontak untuk mencegah transmisi nosokomial antar-bangsal.';
                $recommendedRegimens[] = 'Karbapenem (Meropenem 1g q8h atau Ertapenem 1g q24h) atau Fosfomycin / Nitrofurantoin untuk ISK tanpa komplikasi.';
            }
        }

        // 4. MDR Pseudomonas aeruginosa
        if ($orgKey === 'PSEUDOMONAS_AERUGINOSA') {
            $carbapenemR = ($resultsByAbx['MEROPENEM'] ?? '') === 'RESISTANT';
            $quinoloneR = ($resultsByAbx['CIPROFLOXACIN'] ?? '') === 'RESISTANT';
            $aminoglycosideR = ($resultsByAbx['AMIKACIN'] ?? '') === 'RESISTANT';

            if ($carbapenemR && ($quinoloneR || $aminoglycosideR)) {
                $mdroList[] = 'MDR-Pseudomonas aeruginosa (Multi-Drug Resistant)';
                $precautions[] = 'Kewaspadaan Kontak dan skrining lingkungan wastafel/respirator.';
                $recommendedRegimens[] = 'Ceftolozane-Tazobactam, Ceftazidime-Avibactam, atau Colistin IV inhalasi/sistemik.';
            }
        }

        $isAlertOrganism = count($mdroList) > 0 || ($testedCount > 0 && ($resistantCount / $testedCount) >= 0.6);

        if (count($precautions) === 0) {
            $precautions[] = 'Kewaspadaan Standar (Hand hygiene 5 momen & penggunaan APD sesuai indikasi).';
        }

        return [
            'specimen_id' => $specimenId,
            'organism' => $orgKey,
            'gram_stain' => $gramStain,
            'panel_results' => $panelResults,
            'mdro_classifications' => $mdroList,
            'is_alert_organism' => $isAlertOrganism,
            'infection_control_precautions' => $precautions,
            'recommended_regimens' => $recommendedRegimens,
        ];
    }

    /**
     * Hitung matriks antibiogram agregat dari sekumpulan hasil kultur.
     *
     * @param  list<array{organism: string, antibiotic: string, interpretation: string}>  $historicalResults
     * @return array<string, array<string, array{total_tested: int, susceptible_count: int, percent_susceptible: float}>>
     */
    public function generateHospitalAntibiogramMatrix(array $historicalResults): array
    {
        $matrix = [];

        foreach ($historicalResults as $item) {
            $org = $item['organism'];
            $abx = $item['antibiotic'];
            $interp = $item['interpretation'];

            if (! isset($matrix[$org])) {
                $matrix[$org] = [];
            }
            if (! isset($matrix[$org][$abx])) {
                $matrix[$org][$abx] = [
                    'total_tested' => 0,
                    'susceptible_count' => 0,
                    'percent_susceptible' => 0.0,
                ];
            }

            $matrix[$org][$abx]['total_tested']++;
            if ($interp === 'SUSCEPTIBLE') {
                $matrix[$org][$abx]['susceptible_count']++;
            }
        }

        // Kalkulasi persentase kepekaan
        foreach ($matrix as $org => $abxMap) {
            foreach ($abxMap as $abx => $stats) {
                if ($stats['total_tested'] > 0) {
                    $matrix[$org][$abx]['percent_susceptible'] = round(
                        ($stats['susceptible_count'] / $stats['total_tested']) * 100.0,
                        1
                    );
                }
            }
        }

        return $matrix;
    }
}
