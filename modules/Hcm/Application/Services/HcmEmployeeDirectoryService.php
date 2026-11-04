<?php

declare(strict_types=1);

namespace Modules\Hcm\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Hcm\Contracts\EmployeeDirectory;
use Modules\Hcm\Domain\Models\Employee;

final class HcmEmployeeDirectoryService implements EmployeeDirectory
{
    public function find(int $employeeId): ?array
    {
        $emp = Employee::find($employeeId);
        if (! $emp) {
            return null;
        }

        return [
            'id' => (int) $emp->id,
            'name' => (string) $emp->name,
            'department_id' => (int) $emp->department_id,
            'cost_center' => (string) ($emp->cost_center ?? 'CC-DEFAULT'),
            'active' => $emp->employment_status === 'active',
        ];
    }

    public function isActiveAt(int $employeeId, CarbonImmutable $at): bool
    {
        $emp = Employee::find($employeeId);
        if (! $emp) {
            return false;
        }

        return $emp->employment_status === 'active';
    }

    public function listActiveEmployees(): array
    {
        return Employee::where('employment_status', 'active')
            ->get(['id', 'user_id', 'name'])
            ->map(static fn (Employee $e): array => [
                'id' => (int) $e->id,
                'user_id' => $e->user_id ? (int) $e->user_id : null,
                'name' => (string) $e->name,
            ])
            ->values()
            ->all();
    }
}
