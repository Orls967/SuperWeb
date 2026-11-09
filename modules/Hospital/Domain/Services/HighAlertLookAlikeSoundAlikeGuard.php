<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

/**
 * High-Alert & Look-Alike Sound-Alike (LASA / NORUM) Medication Guard.
 * Mencegah *medication errors* fatal sesuai standar keselamatan pasien KARS & JCI:
 * 1. Deteksi otomatis obat risiko tinggi (High-Alert Medications / HAM).
 * 2. Identifikasi pasangan nama obat mirip bunyi/rupa (LASA / NORUM).
 * 3. Penerapan Tall-Man Lettering standar ISMP untuk visualisasi resep dan label farmasi.
 * 4. Kewajiban verifikasi independen (independent double-check) sebelum dispensing.
 */
class HighAlertLookAlikeSoundAlikeGuard
{
    /**
     * Katalog pasangan nama obat LASA dengan rekomendasi Tall-Man Lettering.
     *
     * @var array<string, array{tall_man: string, look_alike_pair: string, warning: string}>
     */
    private const LASA_CATALOG = [
        'HYDRALAZINE' => [
            'tall_man' => 'hydrALAZINE',
            'look_alike_pair' => 'hydrOXYzine',
            'warning' => 'Antihipertensi vasodilator (Hydralazine) vs Antihistamin sedatif (Hydroxyzine).',
        ],
        'HYDROXYZINE' => [
            'tall_man' => 'hydrOXYzine',
            'look_alike_pair' => 'hydrALAZINE',
            'warning' => 'Antihistamin sedatif (Hydroxyzine) vs Antihipertensi vasodilator (Hydralazine).',
        ],
        'PREDNISONE' => [
            'tall_man' => 'predniSONE',
            'look_alike_pair' => 'prednisoLONE',
            'warning' => 'Kortikosteroid oral prodrug (Prednisone) vs metabolit aktif (Prednisolone).',
        ],
        'PREDNISOLONE' => [
            'tall_man' => 'prednisoLONE',
            'look_alike_pair' => 'predniSONE',
            'warning' => 'Kortikosteroid aktif (Prednisolone) vs prodrug (Prednisone).',
        ],
        'EPHEDRINE' => [
            'tall_man' => 'ePHEDrine',
            'look_alike_pair' => 'EPINEPHRINE',
            'warning' => 'Vasokonstriktor simpatomimetik indirek (Ephedrine) vs katekolamin darurat anafilaksis (Epinephrine).',
        ],
        'EPINEPHRINE' => [
            'tall_man' => 'EPINEPHRINE',
            'look_alike_pair' => 'ePHEDrine',
            'warning' => 'Adrenalin dosis gawat darurat (Epinephrine) vs Ephedrine.',
        ],
        'VINBLASTINE' => [
            'tall_man' => 'vinBLAStine',
            'look_alike_pair' => 'vinCRIStine',
            'warning' => 'Kemoterapi alkaloid vinka: Dosis Vinblastine jauh lebih tinggi dibanding Vincristine; tertukar berakibat fatalitas mielosupresi.',
        ],
        'VINCRISTINE' => [
            'tall_man' => 'vinCRIStine',
            'look_alike_pair' => 'vinBLAStine',
            'warning' => 'Kemoterapi alkaloid vinka: Dosis maksimal Vincristine umumnya 2 mg; overdosis memicu neurotoksisitas fatal.',
        ],
    ];

    /**
     * Kategori High-Alert Medications (HAM).
     */
    private const HIGH_ALERT_CATEGORIES = [
        'INSULIN' => ['HUMALOG', 'LANTUS', 'NOVORAPID', 'ACTRAPID', 'INSULATARD'],
        'CONCENTRATED_ELECTROLYTE' => ['KCL_7_46', 'NACL_3_PCT', 'MGSO4_40_PCT'],
        'ANTICOAGULANT' => ['WARFARIN', 'HEPARIN', 'ENOXAPARIN', 'FONDAPARINUX'],
        'OPIOID_NARCOTIC' => ['FENTANYL', 'MORPHINE', 'PETHIDINE', 'OXYCODONE'],
        'NEUROMUSCULAR_BLOCKER' => ['ROCURONIUM', 'ATRACURIUM', 'VECURONIUM'],
    ];

    /**
     * Memeriksa apakah obat tergolong High-Alert.
     */
    public function isHighAlert(string $drugCode): bool
    {
        $code = strtoupper(trim($drugCode));

        foreach (self::HIGH_ALERT_CATEGORIES as $category => $drugs) {
            if (in_array($code, $drugs, true) || str_contains($code, $category)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Memeriksa apakah obat memiliki potensi LASA / NORUM.
     */
    public function getLasaEvaluation(string $drugName): array
    {
        $nameUpper = strtoupper(trim($drugName));

        if (isset(self::LASA_CATALOG[$nameUpper])) {
            $lasa = self::LASA_CATALOG[$nameUpper];

            return [
                'is_lasa' => true,
                'tall_man_name' => $lasa['tall_man'],
                'confusable_with' => $lasa['look_alike_pair'],
                'clinical_warning' => $lasa['warning'],
                'requires_independent_double_check' => true,
            ];
        }

        return [
            'is_lasa' => false,
            'tall_man_name' => $drugName,
            'confusable_with' => null,
            'clinical_warning' => null,
            'requires_independent_double_check' => false,
        ];
    }

    /**
     * Format nama obat menggunakan konvensi Tall-Man Lettering jika ada di katalog.
     */
    public function formatTallMan(string $drugName): string
    {
        $nameUpper = strtoupper(trim($drugName));

        return self::LASA_CATALOG[$nameUpper]['tall_man'] ?? $drugName;
    }
}
