<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

/**
 * Telemedicine Drug Interaction Advisory & Pharmacist Review Generator.
 * Menghasilkan rekomendasi intervensi klinis terstruktur saat terdeteksi interaksi obat:
 * 1. Pembangkitan lembar advis farmasi klinis (Clinical Pharmacy Advisory Sheet).
 * 2. Formulasi opsi penggantian terapi (*therapeutic interchange options*).
 * 3. Format telaah resep administratif, farmasetik, dan klinis sesuai standar Akreditasi RS.
 */
class TelemedicineDrugInteractionAdvisoryService
{
    public function __construct(
        protected DrugInteractionMatrixService $interactionMatrix,
        protected CrossAllergyImmuneResponseMatrixService $allergyMatrix
    ) {}

    /**
     * Menghasilkan lembar advis telaah farmasi klinis untuk dokter penulis resep.
     *
     * @param  array<int, string>  $drugCodes
     * @return array{
     *     advisory_code: string,
     *     total_interactions_found: int,
     *     requires_pharmacist_consult: bool,
     *     advisory_items: array<int, array{
     *         pair: string,
     *         severity: string,
     *         clinical_guidance: string,
     *         action_required: string
     *     }>,
     *     generated_at: string
     * }
     */
    public function generateAdvisorySheet(array $drugCodes): array
    {
        $interactions = $this->interactionMatrix->scanPrescriptionList($drugCodes);
        $advisoryCode = 'ADV-'.strtoupper(bin2hex(random_bytes(4)));

        $items = [];
        $hasCritical = false;

        foreach ($interactions as $inter) {
            $isContraindicated = $inter['severity'] === 'CONTRAINDICATED';
            if ($isContraindicated) {
                $hasCritical = true;
            }

            $action = match ($inter['severity']) {
                'CONTRAINDICATED' => 'Wajib batalkan atau ganti salah satu obat dengan alternatif aman.',
                'MAJOR' => 'Konfirmasi DPJP diperlukan; jadwalkan monitoring ketat parameter klinis/TDM.',
                default => 'Berikan jeda waktu konsumsi minimal 2-4 jam antar kedua sediaan obat.',
            };

            $items[] = [
                'pair' => "{$inter['drug_a']} + {$inter['drug_b']}",
                'severity' => $inter['severity'],
                'clinical_guidance' => "{$inter['mechanism']} Rekomendasi: {$inter['recommendation']}",
                'action_required' => $action,
            ];
        }

        return [
            'advisory_code' => $advisoryCode,
            'total_interactions_found' => count($items),
            'requires_pharmacist_consult' => $hasCritical || count($items) >= 2,
            'advisory_items' => $items,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
