<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

/**
 * HospitalSurgicalSafeSurgeryChecklistService
 *
 * Mengimplementasikan WHO Surgical Safety Checklist (Permenkes Standar Keselamatan Pasien):
 *   1. Sign In (Sebelum induksi anestesi): Konfirmasi identitas, lokasi sayatan tertera tanda marker, persetujuan informed consent, mesin anestesi & oksimetri berfungsi.
 *   2. Time Out (Sebelum insisi bedah): Seluruh tim memperkenalkan diri, konfirmasi nama pasien, tindakan operasi, antibiotik profilaksis telah diberikan < 60 menit sebelumnya, antisipasi perdarahan kritis (> 500 mL).
 *   3. Sign Out (Sebelum keluar ruang operasi): Pencocokan hitungan instrumen, kasa steril & jarum, pelabelan wadah spesimen biopsi, review rencana pasca-bedah (PACU / ICU).
 */
class HospitalSurgicalSafeSurgeryChecklistService
{
    public const STAGE_SIGN_IN = 'STAGE_SIGN_IN_PRE_INDUCTION';

    public const STAGE_TIME_OUT = 'STAGE_TIME_OUT_PRE_INCISION';

    public const STAGE_SIGN_OUT = 'STAGE_SIGN_OUT_PRE_CLOSURE';

    public const STATUS_CHECKLIST_VERIFIED = 'CHECKLIST_VERIFIED_COMPLETE';

    public const STATUS_CHECKLIST_HALTED = 'CHECKLIST_HALTED_SAFETY_STOP';

    /**
     * Verifikasi fase Sign-In pra-induksi anestesi.
     */
    public function verifySignInPhase(
        bool $patientIdentityConfirmed,
        bool $surgicalSiteMarked,
        bool $anesthesiaSafetyCheckComplete,
        bool $pulseOximeterFunctioning,
        bool $knownAllergyAssessed,
        bool $difficultAirwayRiskAnticipated
    ): array {
        $haltReasons = [];

        if (! $patientIdentityConfirmed) {
            $haltReasons[] = 'Identitas pasien atau gelang rekam medis belum terkonfirmasi oleh pasien/keluarga.';
        }
        if (! $surgicalSiteMarked) {
            $haltReasons[] = 'Penandaan lokasi operasi (surgical site marking) belum dilakukan oleh dokter operator.';
        }
        if (! $anesthesiaSafetyCheckComplete || ! $pulseOximeterFunctioning) {
            $haltReasons[] = 'Pemeriksaan keselamatan mesin anestesi / oksimetri belum siap.';
        }

        $isVerified = empty($haltReasons);

        return [
            'stage' => self::STAGE_SIGN_IN,
            'status' => $isVerified ? self::STATUS_CHECKLIST_VERIFIED : self::STATUS_CHECKLIST_HALTED,
            'is_cleared_for_next_stage' => $isVerified,
            'halt_reasons' => $haltReasons,
            'requires_special_airway_team' => $difficultAirwayRiskAnticipated,
        ];
    }

    /**
     * Verifikasi fase Time-Out pra-insisi kulit kamar operasi.
     */
    public function verifyTimeOutPhase(
        bool $entireTeamIntroduced,
        bool $verbalPatientAndProcedureConfirmed,
        bool $prophylacticAntibioticsAdministeredPastHour,
        bool $essentialImagingDisplayed,
        int $anticipatedBloodLossMl
    ): array {
        $haltReasons = [];

        if (! $entireTeamIntroduced || ! $verbalPatientAndProcedureConfirmed) {
            $haltReasons[] = 'Konfirmasi verbal nama pasien, tindakan bedah, dan peran seluruh tim belum serempak.';
        }
        if (! $prophylacticAntibioticsAdministeredPastHour) {
            $haltReasons[] = 'Antibiotik profilaksis belum diinjeksikan dalam 60 menit sebelum insisi.';
        }

        $isVerified = empty($haltReasons);

        return [
            'stage' => self::STAGE_TIME_OUT,
            'status' => $isVerified ? self::STATUS_CHECKLIST_VERIFIED : self::STATUS_CHECKLIST_HALTED,
            'is_cleared_for_next_stage' => $isVerified,
            'halt_reasons' => $haltReasons,
            'requires_blood_transfusion_standby' => ($anticipatedBloodLossMl >= 500),
            'imaging_ready' => $essentialImagingDisplayed,
        ];
    }

    /**
     * Verifikasi fase Sign-Out pra-penutupan luka dan keluar OK.
     */
    public function verifySignOutPhase(
        int $spongeCountInitial,
        int $spongeCountFinal,
        int $needleCountInitial,
        int $needleCountFinal,
        int $instrumentCountInitial,
        int $instrumentCountFinal,
        bool $specimenCorrectlyLabeledWithPatientId,
        string $postOpDestinationZone
    ): array {
        $haltReasons = [];

        if ($spongeCountInitial !== $spongeCountFinal) {
            $haltReasons[] = sprintf('Hitungan kasa steril selisih (%d awal vs %d akhir). Lakukan pemeriksaan rontgen intraoperatif!', $spongeCountInitial, $spongeCountFinal);
        }
        if ($needleCountInitial !== $needleCountFinal) {
            $haltReasons[] = sprintf('Hitungan jarum bedah selisih (%d awal vs %d akhir). Jangan tutup peritoneum/rongga sebelum lengkap!', $needleCountInitial, $needleCountFinal);
        }
        if ($instrumentCountInitial !== $instrumentCountFinal) {
            $haltReasons[] = sprintf('Hitungan instrumen klem bedah selisih (%d awal vs %d akhir).', $instrumentCountInitial, $instrumentCountFinal);
        }
        if (! $specimenCorrectlyLabeledWithPatientId) {
            $haltReasons[] = 'Label nama pasien pada spesimen patologi anatomi belum terverifikasi.';
        }

        $isVerified = empty($haltReasons);

        return [
            'stage' => self::STAGE_SIGN_OUT,
            'status' => $isVerified ? self::STATUS_CHECKLIST_VERIFIED : self::STATUS_CHECKLIST_HALTED,
            'is_cleared_for_patient_discharge' => $isVerified,
            'halt_reasons' => $haltReasons,
            'post_op_destination' => $postOpDestinationZone,
        ];
    }
}
