<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

class HotelCorporateTravelPolicyComplianceAuditor
{
    /**
     * Memverifikasi apakah reservasi staf mematuhi kebijakan perjalanan dinas korporat (Travel Policy Compliance).
     *
     * @return array{corporate_id: string, room_type_code: string, night_rate_idr: int, max_allowed_rate_idr: int, is_compliant: bool, violation_type: ?string, requires_vp_approval: bool}
     */
    public function auditTravelPolicy(
        string $corporateId,
        string $employeeGrade, // STAFF, MANAGER, DIRECTOR, VP
        string $roomTypeCode,
        int $nightRateIdr
    ): array {
        $allowedCap = match ($employeeGrade) {
            'VP', 'DIRECTOR' => 5000000,
            'MANAGER' => 2500000,
            default => 1250000,
        };

        $allowedRoomTypes = match ($employeeGrade) {
            'VP', 'DIRECTOR' => ['STANDARD', 'DELUXE', 'SUITE', 'VILLA'],
            'MANAGER' => ['STANDARD', 'DELUXE', 'EXECUTIVE'],
            default => ['STANDARD', 'SUPERIOR'],
        };

        $isRateOver = $nightRateIdr > $allowedCap;
        $isTypeDisallowed = ! in_array($roomTypeCode, $allowedRoomTypes, true);

        $violation = null;
        if ($isRateOver && $isTypeDisallowed) {
            $violation = 'RATE_AND_ROOM_TYPE_POLICY_EXCEEDED';
        } elseif ($isRateOver) {
            $violation = 'NIGHT_RATE_EXCEEDED_GRADE_CAP';
        } elseif ($isTypeDisallowed) {
            $violation = 'ROOM_TYPE_DISALLOWED_FOR_GRADE';
        }

        $isCompliant = ($violation === null);
        $requiresVpApproval = ! $isCompliant;

        return [
            'corporate_id' => $corporateId,
            'room_type_code' => $roomTypeCode,
            'night_rate_idr' => $nightRateIdr,
            'max_allowed_rate_idr' => $allowedCap,
            'is_compliant' => $isCompliant,
            'violation_type' => $violation,
            'requires_vp_approval' => $requiresVpApproval,
        ];
    }
}
