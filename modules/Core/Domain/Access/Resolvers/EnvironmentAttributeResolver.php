<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Resolvers;

use DateTimeImmutable;
use Modules\Core\Domain\Access\Values\EnvironmentContext;

/**
 * Environment Attribute Resolver (W8-D53).
 *
 * Resolves temporal boundaries, CIDR classifications, and security postures.
 */
final class EnvironmentAttributeResolver
{
    /**
     * Resolve EnvironmentContext from current request factors.
     */
    public function resolve(string $clientIp = '127.0.0.1', bool $isEmergencyWindow = false): EnvironmentContext
    {
        $now = new DateTimeImmutable;
        $hour = (int) $now->format('G');
        $dayOfWeek = (int) $now->format('N');

        $isBusinessHours = ($hour >= 8 && $hour < 18) && ($dayOfWeek >= 1 && $dayOfWeek <= 5);
        $isWeekend = ($dayOfWeek >= 6);

        $zone = $this->determineSecurityZone($clientIp);
        $threat = $isEmergencyWindow ? 'ORANGE' : 'GREEN';

        return new EnvironmentContext(
            timestamp: $now,
            isBusinessHours: $isBusinessHours,
            isWeekend: $isWeekend,
            isEmergencyMaintenanceWindow: $isEmergencyWindow,
            securityZone: $zone,
            threatLevel: $threat,
            additionalFactors: [
                'resolved_ip' => $clientIp,
                'is_localhost' => ($clientIp === '127.0.0.1' || $clientIp === '::1'),
            ]
        );
    }

    /**
     * Determine network security zone from IP.
     */
    private function determineSecurityZone(string $ip): string
    {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return 'SECURE_BASTION';
        }

        if (str_starts_with($ip, '10.') || str_starts_with($ip, '192.168.') || str_starts_with($ip, '172.16.')) {
            return 'INTERNAL_LAN';
        }

        return 'PUBLIC_INTERNET';
    }
}
