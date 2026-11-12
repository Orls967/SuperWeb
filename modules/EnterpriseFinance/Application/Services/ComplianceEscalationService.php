<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\EnterpriseFinance\Contracts\ComplianceDeadlineSource;
use Psr\Clock\ClockInterface;

class ComplianceEscalationService
{
    /**
     * @param  iterable<ComplianceDeadlineSource>  $sources
     */
    public function __construct(
        protected readonly iterable $sources = [],
        protected readonly ?OutboxBusInterface $outboxBus = null,
        protected readonly ?ClockInterface $clock = null
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Memindai seluruh sumber kepatuhan dan memicu eskalasi berjenjang (H-30, H-14, H-7, H-1, Overdue).
     *
     * @return array{
     *     total_scanned: int,
     *     escalations_sent: int,
     *     tiers: array<string, int>
     * }
     */
    public function scanAndEscalate(?string $asOfDate = null): array
    {
        $currentDate = $asOfDate !== null ? CarbonImmutable::parse($asOfDate) : $this->now();
        $dateStr = $currentDate->format('Y-m-d');

        $totalScanned = 0;
        $escalationsSent = 0;
        $tierCounts = [
            'H-30' => 0,
            'H-14' => 0,
            'H-7' => 0,
            'H-1' => 0,
            'OVERDUE' => 0,
        ];

        foreach ($this->sources as $source) {
            $deadlines = $source->getDeadlines($dateStr);
            foreach ($deadlines as $d) {
                $totalScanned++;
                $dueDate = CarbonImmutable::parse($d['due_date']);
                $diffDays = (int) $currentDate->diffInDays($dueDate, false);

                $tier = null;
                if ($diffDays < 0) {
                    $tier = 'OVERDUE';
                } elseif ($diffDays <= 1) {
                    $tier = 'H-1';
                } elseif ($diffDays <= 7) {
                    $tier = 'H-7';
                } elseif ($diffDays <= 14) {
                    $tier = 'H-14';
                } elseif ($diffDays <= 30) {
                    $tier = 'H-30';
                }

                if ($tier !== null) {
                    $tierCounts[$tier]++;
                    $escalationsSent++;

                    if ($this->outboxBus !== null) {
                        $this->outboxBus->record(
                            'enterprise.compliance.escalated.v1',
                            [
                                'source' => $source->getSourceName(),
                                'deadline_code' => $d['code'],
                                'title' => $d['title'],
                                'category' => $d['category'],
                                'tier' => $tier,
                                'due_date' => $d['due_date'],
                                'entity_code' => $d['entity_code'],
                                'responsible_role' => $d['responsible_role'],
                                'penalty_estimate_minor' => $d['penalty_estimate_minor'],
                            ],
                            $d['code']
                        );
                    }
                }
            }
        }

        return [
            'total_scanned' => $totalScanned,
            'escalations_sent' => $escalationsSent,
            'tiers' => $tierCounts,
        ];
    }
}
