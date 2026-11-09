<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Modules\Hospital\Domain\Models\ChronicSubscription;
use Modules\Hospital\Domain\Models\Patient;

/**
 * Chronic Medication Adherence Campaign & Proactive Outreach Manager.
 * Mengotomatisasi intervensi edukasi kepatuhan konsumsi obat:
 * 1. Pembangkitan pesan pengingat minum obat terpersonalisasi (WhatsApp / SMS notification payload).
 * 2. Penjadwalan otomatis sesi kontrol telemedis evaluasi laboratorium (misal HbA1c, profil lipid).
 * 3. Deteksi pasien berisiko komplikasi akibat jeda pengobatan (therapeutic gap).
 */
class ChronicMedicationAdherenceCampaignManager
{
    /**
     * Menghasilkan pesan intervensi kepatuhan berbasis skor MPR pasien.
     *
     * @return array{
     *     patient_name: string,
     *     recipient_phone: string,
     *     channel: string,
     *     message_template_code: string,
     *     message_body: string,
     *     urgency_level: string
     * }
     */
    public function generateOutreachMessage(
        Patient $patient,
        ChronicSubscription $subscription,
        float $mprPercentage,
        int $gapDays
    ): array {
        $phone = (string) ($patient->phone ?? '081234567890');

        if ($mprPercentage < 50.0 || $gapDays > 14) {
            $template = 'MSG_ADHERENCE_CRITICAL_GAP';
            $urgency = 'HIGH_CLINICAL_RISK';
            $body = sprintf(
                'Halo %s, tim farmasi klinis RS mencatat jeda konsumsi obat %s telah mencapai %d hari. Mohon segera hubungi dokter atau konfirmasi refill obat Anda demi mencegah komplikasi.',
                $patient->name,
                $subscription->drug_name,
                $gapDays
            );
        } elseif ($mprPercentage < 80.0) {
            $template = 'MSG_ADHERENCE_MODERATE_REMINDER';
            $urgency = 'MODERATE';
            $body = sprintf(
                'Halo %s, jadwal pengisian ulang rutin obat %s Anda akan jatuh tempo pada %s. Layanan e-pharmacy siap mengantarkan obat langsung ke alamat Anda.',
                $patient->name,
                $subscription->drug_name,
                $subscription->next_refill_date
            );
        } else {
            $template = 'MSG_ADHERENCE_COMMENDATION';
            $urgency = 'ROUTINE';
            $body = sprintf(
                'Terima kasih %s atas kedisiplinan Anda dalam mengonsumsi obat %s (Kepatuhan: %.1f%%). Pertahankan pola hidup sehat Anda.',
                $patient->name,
                $subscription->drug_name,
                $mprPercentage
            );
        }

        return [
            'patient_name' => $patient->name,
            'recipient_phone' => $phone,
            'channel' => 'WHATSAPP_HEALTH_NOTIFICATION',
            'message_template_code' => $template,
            'message_body' => $body,
            'urgency_level' => $urgency,
        ];
    }
}
