<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

/**
 * Algoritma A7 Shared: Disrupted Passenger Reaccommodator.
 */
class DisruptedPassengerReaccommodator
{
    /**
     * Prioritaskan manifes penumpang terdampak dan terbitkan sertifikat deterministik.
     *
     * @param  array<int, array{id: int|string, name: string, tier: string, cabin: string, has_connection: bool, special: ?string, booking_time?: int}>  $passengers
     */
    public function prioritize(array $passengers): PassengerReaccommodationResult
    {
        $scored = [];

        foreach ($passengers as $p) {
            $score = 0;

            $special = strtoupper((string) ($p['special'] ?? ''));
            if ($special === 'UMNR') {
                $score += 20000;
            } elseif (in_array($special, ['WCHR', 'WCHS', 'WCHC', 'MEDA'], true)) {
                $score += 15000;
            } elseif ($special === 'INFANT') {
                $score += 10000;
            }

            $tier = strtolower((string) ($p['tier'] ?? 'classic'));
            $score += match ($tier) {
                'platinum' => 10000,
                'gold' => 5000,
                'silver' => 2500,
                default => 500,
            };

            $cabin = strtolower((string) ($p['cabin'] ?? 'economy'));
            $score += match ($cabin) {
                'first' => 4000,
                'business' => 3000,
                'premium_economy' => 1500,
                default => 500,
            };

            if (! empty($p['has_connection'])) {
                $score += 8000;
            }

            $bookingTime = (int) ($p['booking_time'] ?? 0);

            $scored[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'priority_score' => $score,
                'tier' => $tier,
                'cabin' => $cabin,
                'has_connection' => (bool) ($p['has_connection'] ?? false),
                'special' => $p['special'] ?? null,
                'booking_time' => $bookingTime,
            ];
        }

        usort($scored, function ($a, $b) {
            if ($b['priority_score'] !== $a['priority_score']) {
                return $b['priority_score'] <=> $a['priority_score'];
            }

            return $a['booking_time'] <=> $b['booking_time'];
        });

        $cert = PassengerReaccommodationCertificate::create(count($scored), $scored);

        return new PassengerReaccommodationResult(count($scored), $scored, $cert);
    }
}
