<?php

declare(strict_types=1);

namespace Modules\Hcm\Contracts;

use Carbon\CarbonImmutable;

interface EmployeeDirectory
{
    /**
     * @return array{id: int, name: string, department_id: int, cost_center: string, active: bool}|null
     */
    public function find(int $employeeId): ?array;

    public function isActiveAt(int $employeeId, CarbonImmutable $at): bool;

    /**
     * @return list<array{id: int, user_id: int|null, name: string}>
     */
    public function listActiveEmployees(): array;
}
