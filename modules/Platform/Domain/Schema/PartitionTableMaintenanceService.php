<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Schema;

final class PartitionTableMaintenanceService
{
    /**
     * Generates monthly partition creation SQL statements for high-volume ledger tables.
     *
     * @param  string  $tableName  e.g. 'c_transactions'
     * @param  int  $year  e.g. 2026
     * @param  int  $startMonth  1..12
     * @param  int  $monthsAhead  e.g. 3
     * @return list<string> DDL statements
     */
    public function generateMonthlyPartitions(string $tableName, int $year, int $startMonth, int $monthsAhead = 3): array
    {
        $statements = [];

        for ($i = 0; $i < $monthsAhead; $i++) {
            $currentMonth = $startMonth + $i;
            $currentYear = $year;
            if ($currentMonth > 12) {
                $currentMonth -= 12;
                $currentYear++;
            }

            $nextMonth = $currentMonth + 1;
            $nextYear = $currentYear;
            if ($nextMonth > 12) {
                $nextMonth = 1;
                $nextYear++;
            }

            $partitionName = sprintf('p_%04d_%02d', $currentYear, $currentMonth);
            $upperBoundDate = sprintf('%04d-%02d-01', $nextYear, $nextMonth);

            $statements[] = "ALTER TABLE `{$tableName}` ADD PARTITION (PARTITION `{$partitionName}` VALUES LESS THAN (TO_DAYS('{$upperBoundDate}')));";
        }

        return $statements;
    }
}
