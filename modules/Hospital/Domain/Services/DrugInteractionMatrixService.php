<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Modules\Hospital\Domain\Models\DrugInteractionRule;

/**
 * Drug Interaction Matrix Service.
 * Menyediakan repositori komprehensif interaksi farmakokinetik & farmakodinamik antarobat:
 * - Klasifikasi tingkat keparahan: CONTRAINDICATED, MAJOR, MODERATE, MINOR.
 * - Dampak klinis: inhibisi enzim CYP450, sinergi depresi SSP, perpanjangan interval QTc, nefrotoksisitas aditif.
 * - Rekomendasi mitigasi dan panduan pemantauan terapeutik (TDM).
 */
class DrugInteractionMatrixService
{
    /**
     * Matriks statis interaksi berisiko tinggi standar klinis rumah sakit.
     *
     * @var array<string, array{severity: string, mechanism: string, recommendation: string}>
     */
    private const CLINICAL_INTERACTIONS_CATALOG = [
        'WARFARIN:ASPIRIN' => [
            'severity' => 'MAJOR',
            'mechanism' => 'Penghambatan fungsi trombosit oleh Aspirin dikombinasikan dengan antikoagulan Warfarin melipatgandakan risiko perdarahan masif.',
            'recommendation' => 'Hindari kombinasi kecuali ada indikasi mekanik katup jantung dengan pemantauan ketat INR.',
        ],
        'WARFARIN:CIPROFLOXACIN' => [
            'severity' => 'MAJOR',
            'mechanism' => 'Ciprofloxacin menghambat metabolisme Warfarin pada enzim CYP1A2 dan CYP3A4 serta mengurangi flora usus penghasil vitamin K, meningkatkan INR secara tajam.',
            'recommendation' => 'Turunkan dosis Warfarin 30-50% dan periksa INR serial setiap 48 jam.',
        ],
        'SIMVASTATIN:AMLODIPINE' => [
            'severity' => 'MODERATE',
            'mechanism' => 'Amlodipine menghambat CYP3A4 secara parsial, meningkatkan kadar plasma Simvastatin hingga 77% dengan risiko miopati.',
            'recommendation' => 'Batasi dosis Simvastatin maksimal 20 mg/hari bila digunakan bersamaan dengan Amlodipine.',
        ],
        'CLOPIDOGREL:OMEPRAZOLE' => [
            'severity' => 'MAJOR',
            'mechanism' => 'Omeprazole menghambat bioaktivasi Clopidogrel melalui CYP2C19, menurunkan efek antiplatelet dan meningkatkan risiko trombosis stent.',
            'recommendation' => 'Ganti Omeprazole dengan Pantoprazole yang memiliki interaksi minimal terhadap CYP2C19.',
        ],
        'METHOTREXATE:NSAID' => [
            'severity' => 'CONTRAINDICATED',
            'mechanism' => 'NSAID menurunkan klirens renal Methotrexate melalui inhibisi sintesis prostaglandin ginjal, menyebabkan toksisitas sumsum tulang akut.',
            'recommendation' => 'Dilarang meresepkan NSAID dosis tinggi bersama Methotrexate. Gunakan Paracetamol sebagai analgesik lini pertama.',
        ],
        'DIGOXIN:VERAPAMIL' => [
            'severity' => 'MAJOR',
            'mechanism' => 'Verapamil menghambat sekresi tubular Digoxin via P-glikoprotein, melipatgandakan konsentrasi serum Digoxin.',
            'recommendation' => 'Turunkan dosis Digoxin sebesar 50% dan periksa kadar serum Digoxin dalam 5-7 hari.',
        ],
        'SILDENAFIL:NITROGLYCERIN' => [
            'severity' => 'CONTRAINDICATED',
            'mechanism' => 'Kombinasi inhibitor PDE-5 dengan donor nitrat menghasilkan akumulasi cGMP masif yang memicu hipotensi kolaps dan henti jantung.',
            'recommendation' => 'Kontraindikasi mutlak. Jangan berikan Nitrat dalam 24 jam setelah konsumsi Sildenafil.',
        ],
        'LITHIUM:HYDROCHLOROTHIAZIDE' => [
            'severity' => 'MAJOR',
            'mechanism' => 'Diuretik tiazid memicu natriuresis sehingga tubulus proksimal meningkatkan reabsorpsi Lithium, memicu intoksikasi Lithium berat.',
            'recommendation' => 'Turunkan dosis Lithium 50% dan monitor kadar serum Lithium mingguan.',
        ],
        'ACE_INHIBITOR:POTASSIUM_SPARING' => [
            'severity' => 'MAJOR',
            'mechanism' => 'Kombinasi ACE inhibitor (misal Captopril, Ramipril) dengan Spironolactone atau suplemen Kalium menyebabkan hiperkalemia fatal.',
            'recommendation' => 'Pantau ketat kalium serum. Hentikan suplemen kalium rutin.',
        ],
        'CIPROFLOXACIN:THEOPHYLLINE' => [
            'severity' => 'MAJOR',
            'mechanism' => 'Ciprofloxacin menghambat klirens Theophylline via CYP1A2, meningkatkan risiko kejang toksik theophylline.',
            'recommendation' => 'Kurangi dosis Theophylline hingga 30-50% dan pantau konsentrasi plasma.',
        ],
    ];

    /**
     * Memeriksa potensi interaksi antara dua obat.
     *
     * @return array{
     *     has_interaction: bool,
     *     severity: string|null,
     *     mechanism: string|null,
     *     recommendation: string|null
     * }
     */
    public function checkPair(string $drugCodeA, string $drugCodeB): array
    {
        $codeA = strtoupper(trim($drugCodeA));
        $codeB = strtoupper(trim($drugCodeB));

        // Cek aturan kustom di database
        $rule = DrugInteractionRule::where('is_active', true)
            ->where(function ($q) use ($codeA, $codeB): void {
                $q->where(fn ($sub) => $sub->where('drug_a_code', $codeA)->where('drug_b_code', $codeB))
                    ->orWhere(fn ($sub) => $sub->where('drug_a_code', $codeB)->where('drug_b_code', $codeA));
            })
            ->first();

        if ($rule) {
            return [
                'has_interaction' => true,
                'severity' => $rule->interaction_severity,
                'mechanism' => $rule->clinical_mechanism,
                'recommendation' => $rule->recommendation,
            ];
        }

        // Cek katalog statis
        $key1 = "{$codeA}:{$codeB}";
        $key2 = "{$codeB}:{$codeA}";

        if (isset(self::CLINICAL_INTERACTIONS_CATALOG[$key1])) {
            $item = self::CLINICAL_INTERACTIONS_CATALOG[$key1];

            return [
                'has_interaction' => true,
                'severity' => $item['severity'],
                'mechanism' => $item['mechanism'],
                'recommendation' => $item['recommendation'],
            ];
        }

        if (isset(self::CLINICAL_INTERACTIONS_CATALOG[$key2])) {
            $item = self::CLINICAL_INTERACTIONS_CATALOG[$key2];

            return [
                'has_interaction' => true,
                'severity' => $item['severity'],
                'mechanism' => $item['mechanism'],
                'recommendation' => $item['recommendation'],
            ];
        }

        return [
            'has_interaction' => false,
            'severity' => null,
            'mechanism' => null,
            'recommendation' => null,
        ];
    }

    /**
     * Memindai seluruh daftar obat dalam satu resep secara kombinatorial.
     *
     * @param  array<int, string>  $drugCodes
     * @return array<int, array{
     *     drug_a: string,
     *     drug_b: string,
     *     severity: string,
     *     mechanism: string,
     *     recommendation: string
     * }>
     */
    public function scanPrescriptionList(array $drugCodes): array
    {
        $interactions = [];
        $uniqueCodes = array_values(array_unique(array_map('strtoupper', array_map('trim', $drugCodes))));
        $count = count($uniqueCodes);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $result = $this->checkPair($uniqueCodes[$i], $uniqueCodes[$j]);
                if ($result['has_interaction']) {
                    $interactions[] = [
                        'drug_a' => $uniqueCodes[$i],
                        'drug_b' => $uniqueCodes[$j],
                        'severity' => (string) $result['severity'],
                        'mechanism' => (string) $result['mechanism'],
                        'recommendation' => (string) $result['recommendation'],
                    ];
                }
            }
        }

        return $interactions;
    }
}
