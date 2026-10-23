<?php

declare(strict_types=1);

namespace Modules\Hcm\Application\Actions;

use InvalidArgumentException;
use Modules\Hcm\Domain\Models\AttendanceLog;
use Modules\Hcm\Domain\Models\Employee;
use Modules\Hcm\Domain\Models\ShiftSchedule;

class RecordBiometricShiftAttendanceAction
{
    /**
     * @param  string  $logDate  YYYY-MM-DD
     * @param  string  $clockIn  YYYY-MM-DD HH:MM:SS or HH:MM:SS
     * @param  string|null  $clockOut  YYYY-MM-DD HH:MM:SS or HH:MM:SS
     * @return array{
     *     log: AttendanceLog,
     *     late_minutes: int,
     *     overtime_hours: float,
     *     status: string
     * }
     */
    public function execute(
        Employee $employee,
        string $logDate,
        string $clockIn,
        ?string $clockOut = null,
        ?ShiftSchedule $shift = null
    ): array {
        if (empty($logDate) || empty($clockIn)) {
            throw new InvalidArgumentException('Log date and clock in timestamp are required.');
        }

        $clockInTs = strtotime($clockIn);
        $lateMinutes = 0;
        $overtimeHours = 0.0;

        if ($shift) {
            $shiftStartTs = strtotime($logDate.' '.$shift->start_time);
            if ($clockInTs > $shiftStartTs) {
                $lateMinutes = (int) round(($clockInTs - $shiftStartTs) / 60);
            }

            if ($clockOut) {
                $clockOutTs = strtotime($clockOut);
                $shiftEndTs = strtotime($logDate.' '.$shift->end_time);
                if ($clockOutTs > $shiftEndTs) {
                    $overtimeHours = round(($clockOutTs - $shiftEndTs) / 3600.0, 2);
                }
            }
        }

        $status = $lateMinutes > 0 ? 'late' : 'present';

        $log = AttendanceLog::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'log_date' => $logDate,
            ],
            [
                'shift_id' => $shift?->id,
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'late_minutes' => $lateMinutes,
                'overtime_hours' => $overtimeHours,
                'status' => $status,
            ]
        );

        return [
            'log' => $log,
            'late_minutes' => $lateMinutes,
            'overtime_hours' => $overtimeHours,
            'status' => $status,
        ];
    }
}
