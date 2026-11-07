<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\HotelApoaReport;

/**
 * Tindakan Audit Kepatuhan Pelaporan APOA Imigrasi 1x24 Jam Tamu Asing.
 */
final class AuditForeignGuestApoaSubmissionAction
{
    /**
     * @return array{total_audited: int, overdue_unsubmitted_count: int, is_fully_compliant: bool}
     */
    public function execute(string|int $propertyId): array
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $overdueReports = HotelApoaReport::where('status', 'pending')
            ->where('check_in_date', '<=', $yesterday)
            ->get();

        $overdueCount = $overdueReports->count();
        $totalReports = HotelApoaReport::count();

        return [
            'total_audited' => $totalReports,
            'overdue_unsubmitted_count' => $overdueCount,
            'is_fully_compliant' => $overdueCount === 0,
        ];
    }
}
