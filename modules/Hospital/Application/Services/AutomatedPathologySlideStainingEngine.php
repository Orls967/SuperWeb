<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use InvalidArgumentException;

/**
 * Mesin Orkestrasi Pewarnaan Preparat Patologi Anatomi Otomatis (Autostainer Robot Engine) (Fase 105).
 *
 * Mengendalikan alur kerja robot pemulasan preparat mikroskopis:
 * 1. Protokol Hematoksilin & Eosin (H&E):
 *    - Deparafinisasi: Xylene I, II, III (3x 5 menit)
 *    - Rehidrasi: Ethanol bertingkat 100%, 95%, 80%, 70% hingga air suling
 *    - Pulasan Inti: Hematoxylin Mayer/Harris (5-8 menit)
 *    - Diferensiasi: Asam-alkohol 0.5% (2-3 celup)
 *    - Bluing: Air mengalir / Lithium Carbonate 0.1% (3 menit)
 *    - Pulasan Sitoplasma: Eosin Y 1% alkoholik (1-2 menit)
 *    - Dehidrasi & Clearing: Alkohol absolut bertingkat -> Xylene I, II, III
 *    - Coverslipping: Media perlekatan DPX / Entellan
 * 2. Protokol Khusus:
 *    - Ziehl-Neelsen (BTA Mycobacterium tuberculosis)
 *    - Periodic Acid Schiff (PAS untuk jamur & membran basalis glomerulus)
 *    - Giemsa (Malaria, Helicobacter pylori, sel darah)
 * 3. Pelacakan Kapasitas Bak Reagen & Deteksi Kontaminasi Silang (Reagent Carryover Fatigue):
 *    - Menghitung jumlah preparat (slide count) yang telah melalui bak pewarna
 *    - Peringatan penggantian reagen bila melampaui kapasitas maksimal (misal: 500 slide per wadah).
 */
class AutomatedPathologySlideStainingEngine
{
    /**
     * Kapasitas maksimum slide sebelum reagen wajib diganti (exhaustion limit).
     */
    public const MAX_SLIDES_PER_REAGENT_BATH = 500;

    /**
     * Definisi tahapan protokol pewarnaan standar.
     *
     * @var array<string, list<array{step: int, reagent: string, duration_seconds: int, bath_type: string}>>
     */
    private const PROTOCOLS = [
        'HEMATOXYLIN_EOSIN' => [
            ['step' => 1, 'reagent' => 'Xylene 1 (Deparaffinization)', 'duration_seconds' => 300, 'bath_type' => 'SOLVENT'],
            ['step' => 2, 'reagent' => 'Xylene 2 (Deparaffinization)', 'duration_seconds' => 300, 'bath_type' => 'SOLVENT'],
            ['step' => 3, 'reagent' => 'Xylene 3 (Deparaffinization)', 'duration_seconds' => 300, 'bath_type' => 'SOLVENT'],
            ['step' => 4, 'reagent' => 'Ethanol 100% (Rehydration)', 'duration_seconds' => 120, 'bath_type' => 'ALCOHOL'],
            ['step' => 5, 'reagent' => 'Ethanol 95% (Rehydration)', 'duration_seconds' => 120, 'bath_type' => 'ALCOHOL'],
            ['step' => 6, 'reagent' => 'Ethanol 70% (Rehydration)', 'duration_seconds' => 120, 'bath_type' => 'ALCOHOL'],
            ['step' => 7, 'reagent' => 'Deionized Water Rinse', 'duration_seconds' => 60, 'bath_type' => 'WATER'],
            ['step' => 8, 'reagent' => 'Mayers Hematoxylin (Nuclear Stain)', 'duration_seconds' => 360, 'bath_type' => 'DYE'],
            ['step' => 9, 'reagent' => 'Running Tap Water Wash', 'duration_seconds' => 120, 'bath_type' => 'WATER'],
            ['step' => 10, 'reagent' => 'Acid Alcohol 0.5% (Differentiation)', 'duration_seconds' => 5, 'bath_type' => 'ACID'],
            ['step' => 11, 'reagent' => 'Lithium Carbonate 0.1% (Bluing)', 'duration_seconds' => 90, 'bath_type' => 'BASE'],
            ['step' => 12, 'reagent' => 'Running Tap Water Wash', 'duration_seconds' => 120, 'bath_type' => 'WATER'],
            ['step' => 13, 'reagent' => 'Eosin Y 1% (Cytoplasmic Stain)', 'duration_seconds' => 90, 'bath_type' => 'DYE'],
            ['step' => 14, 'reagent' => 'Ethanol 95% (Dehydration)', 'duration_seconds' => 60, 'bath_type' => 'ALCOHOL'],
            ['step' => 15, 'reagent' => 'Ethanol 100% (Dehydration)', 'duration_seconds' => 120, 'bath_type' => 'ALCOHOL'],
            ['step' => 16, 'reagent' => 'Xylene Clear 1 (Clearing)', 'duration_seconds' => 180, 'bath_type' => 'SOLVENT'],
            ['step' => 17, 'reagent' => 'Xylene Clear 2 (Clearing)', 'duration_seconds' => 180, 'bath_type' => 'SOLVENT'],
        ],
        'ZIEHL_NEELSEN_BTA' => [
            ['step' => 1, 'reagent' => 'Xylene 1 (Deparaffinization)', 'duration_seconds' => 300, 'bath_type' => 'SOLVENT'],
            ['step' => 2, 'reagent' => 'Ethanol 100%', 'duration_seconds' => 120, 'bath_type' => 'ALCOHOL'],
            ['step' => 3, 'reagent' => 'Ethanol 95%', 'duration_seconds' => 120, 'bath_type' => 'ALCOHOL'],
            ['step' => 4, 'reagent' => 'Carbol Fuchsin (Heating/Staining)', 'duration_seconds' => 600, 'bath_type' => 'DYE'],
            ['step' => 5, 'reagent' => 'Deionized Water Rinse', 'duration_seconds' => 60, 'bath_type' => 'WATER'],
            ['step' => 6, 'reagent' => 'Acid Alcohol 3% (Decolorization)', 'duration_seconds' => 60, 'bath_type' => 'ACID'],
            ['step' => 7, 'reagent' => 'Water Rinse', 'duration_seconds' => 60, 'bath_type' => 'WATER'],
            ['step' => 8, 'reagent' => 'Methylene Blue 0.3% (Counterstain)', 'duration_seconds' => 120, 'bath_type' => 'DYE'],
            ['step' => 9, 'reagent' => 'Dehydration & Clearing', 'duration_seconds' => 300, 'bath_type' => 'SOLVENT'],
        ],
        'PERIODIC_ACID_SCHIFF' => [
            ['step' => 1, 'reagent' => 'Deparaffinization & Hydration', 'duration_seconds' => 600, 'bath_type' => 'SOLVENT'],
            ['step' => 2, 'reagent' => 'Periodic Acid 0.5% (Oxidation)', 'duration_seconds' => 300, 'bath_type' => 'ACID'],
            ['step' => 3, 'reagent' => 'Distilled Water Wash', 'duration_seconds' => 120, 'bath_type' => 'WATER'],
            ['step' => 4, 'reagent' => 'Schiff Reagent (Incubation in Dark)', 'duration_seconds' => 900, 'bath_type' => 'DYE'],
            ['step' => 5, 'reagent' => 'Sulfurous Water Rinse', 'duration_seconds' => 180, 'bath_type' => 'WATER'],
            ['step' => 6, 'reagent' => 'Mayers Hematoxylin (Counterstain)', 'duration_seconds' => 180, 'bath_type' => 'DYE'],
            ['step' => 7, 'reagent' => 'Bluing, Dehydration & Clearing', 'duration_seconds' => 600, 'bath_type' => 'SOLVENT'],
        ],
    ];

    /**
     * Rencanakan jalannya batch pewarnaan untuk sejumlah kaca preparat.
     *
     * @param  string  $protocolName  Nama protokol ('HEMATOXYLIN_EOSIN', 'ZIEHL_NEELSEN_BTA', 'PERIODIC_ACID_SCHIFF')
     * @param  int  $slideCount  Jumlah preparat dalam rak (carrier basket)
     * @param  array<string, int>  $currentBathUsage  Peta nama reagen => jumlah slide historis yang telah diproses
     * @return array{
     *     protocol: string,
     *     total_steps: int,
     *     total_duration_minutes: float,
     *     slide_count: int,
     *     steps: list<array{step: int, reagent: string, duration_seconds: int, bath_type: string}>,
     *     reagent_exhaustion_warnings: list<string>,
     *     is_safe_to_execute: bool
     * }
     */
    public function planStainingRun(
        string $protocolName,
        int $slideCount,
        array $currentBathUsage = []
    ): array {
        $protocolKey = strtoupper(trim($protocolName));
        if (! isset(self::PROTOCOLS[$protocolKey])) {
            throw new InvalidArgumentException("Protokol pewarnaan '{$protocolName}' tidak dikenal.");
        }

        if ($slideCount <= 0 || $slideCount > 60) {
            throw new InvalidArgumentException('Kapasitas rak autostainer adalah 1 hingga 60 slide per siklus.');
        }

        $steps = self::PROTOCOLS[$protocolKey];
        $totalSeconds = 0;
        $warnings = [];

        foreach ($steps as $step) {
            $totalSeconds += $step['duration_seconds'];
            $reagentName = $step['reagent'];

            $priorUsage = $currentBathUsage[$reagentName] ?? 0;
            $newUsage = $priorUsage + $slideCount;

            if ($newUsage >= self::MAX_SLIDES_PER_REAGENT_BATH) {
                $warnings[] = sprintf(
                    'Reagen [%s] telah memproses %d slide (batas: %d). Penggantian cairan wajib dilakukan sebelum memulai siklus.',
                    $reagentName,
                    $newUsage,
                    self::MAX_SLIDES_PER_REAGENT_BATH
                );
            }
        }

        $totalMinutes = round($totalSeconds / 60.0, 1);
        $isSafe = count($warnings) === 0;

        return [
            'protocol' => $protocolKey,
            'total_steps' => count($steps),
            'total_duration_minutes' => $totalMinutes,
            'slide_count' => $slideCount,
            'steps' => $steps,
            'reagent_exhaustion_warnings' => $warnings,
            'is_safe_to_execute' => $isSafe,
        ];
    }

    /**
     * Evaluasi kualitas hasil pulasan mikroskopis (QC Staining Checklist).
     *
     * @param  bool  $nuclearDetailSharp  Ketajaman kromatin & nukleolus
     * @param  bool  $cytoplasmDifferentiated  Kontras eosin antara serat kolagen (pink pekat) dan sel eritrosit (merah terang)
     * @param  bool  $noReagentPrecipitate  Bebas dari kristal atau endapan zat pewarna
     * @param  bool  $noChatterOrFolds  Bebas dari lipatan jaringan dan chatter marks mikrotom
     * @return array{
     *     stain_quality_score: int,
     *     grade: string,
     *     is_diagnostic_quality: bool,
     *     feedback: list<string>
     * }
     */
    public function evaluateStainingQuality(
        bool $nuclearDetailSharp,
        bool $cytoplasmDifferentiated,
        bool $noReagentPrecipitate,
        bool $noChatterOrFolds
    ): array {
        $score = 0;
        $feedback = [];

        if ($nuclearDetailSharp) {
            $score += 35;
        } else {
            $feedback[] = 'Detail kromatin inti kabur/pucat; evaluasi waktu pulasan hematoksilin atau diferensiasi asam-alkohol.';
        }

        if ($cytoplasmDifferentiated) {
            $score += 30;
        } else {
            $feedback[] = 'Diferensiasi sitoplasma kurang kontras; sesuaikan pH eosin atau waktu pencucian alkohol 95%.';
        }

        if ($noReagentPrecipitate) {
            $score += 20;
        } else {
            $feedback[] = 'Ditemukan presipitat pewarna pada kaca; reagen pewarna harus disaring (filter Whatman No. 1) atau diganti.';
        }

        if ($noChatterOrFolds) {
            $score += 15;
        } else {
            $feedback[] = 'Ditemukan artefak mekanis mikrotom (lipatan/chatter); periksa ketajaman pisau mikrotom dan suhu waterbath.';
        }

        $grade = 'A (OPTIMAL)';
        $isDiagnostic = true;

        if ($score < 70) {
            $grade = 'C (UNACCEPTABLE / RE-STAIN)';
            $isDiagnostic = false;
        } elseif ($score < 85) {
            $grade = 'B (SUBOPTIMAL ACCEPTABLE)';
            $isDiagnostic = true;
        }

        return [
            'stain_quality_score' => $score,
            'grade' => $grade,
            'is_diagnostic_quality' => $isDiagnostic,
            'feedback' => $feedback,
        ];
    }
}
