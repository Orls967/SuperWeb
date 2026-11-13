<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use InvalidArgumentException;
use Modules\Wms\Domain\Certificates\CycleCountScheduleCertificate;
use Modules\Wms\Domain\Certificates\CycleCountScheduleCertificateVerifier;

/**
 * H3 — CycleCountScheduler: penjadwalan audit stok berkala berdasarkan interval ABC & overdue.
 */
final class CycleCountScheduler
{
    private const array INTERVALS = [
        'A' => 30,
        'B' => 90,
        'C' => 360,
    ];

    public function __construct(
        private readonly CycleCountScheduleCertificateVerifier $verifier = new CycleCountScheduleCertificateVerifier,
    ) {}

    /**
     * @param  list<array{id: string|int, abcClass: string, lastCountedDay: int}>  $items
     * @return array{
     *     selected: list<array{id: string, abcClass: string, overdue: int}>,
     *     carried_over: list<array{id: string, abcClass: string, overdue: int}>,
     *     certificate: CycleCountScheduleCertificate
     * }
     */
    public function schedule(int $today, array $items, int $capacity): array
    {
        if ($capacity < 0) {
            throw new InvalidArgumentException('Kapasitas cycle count tidak boleh negatif.');
        }

        $dueItems = [];
        foreach ($items as $item) {
            $class = strtoupper(trim($item['abcClass']));
            if (! isset(self::INTERVALS[$class])) {
                throw new InvalidArgumentException("Kelas ABC tidak valid: '{$item['abcClass']}'.");
            }

            $overdue = $today - ((int) $item['lastCountedDay'] + self::INTERVALS[$class]);
            if ($overdue >= 0) {
                $dueItems[] = [
                    'id' => (string) $item['id'],
                    'abcClass' => $class,
                    'overdue' => $overdue,
                ];
            }
        }

        usort($dueItems, static function (array $a, array $b): int {
            return [$b['overdue'], $a['abcClass'], $a['id']]
                <=> [$a['overdue'], $b['abcClass'], $b['id']];
        });

        $selected = array_slice($dueItems, 0, $capacity);
        $carriedOver = array_slice($dueItems, $capacity);

        $certificate = CycleCountScheduleCertificate::create(
            today: $today,
            capacity: $capacity,
            selected: $selected,
            carriedOver: $carriedOver,
        );
        $this->verifier->verify($certificate);

        return [
            'selected' => $selected,
            'carried_over' => $carriedOver,
            'certificate' => $certificate,
        ];
    }
}
