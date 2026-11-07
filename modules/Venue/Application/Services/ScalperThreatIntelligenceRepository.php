<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\ScalperBlacklistLog;

final class ScalperThreatIntelligenceRepository
{
    /**
     * @var array<int, string>
     */
    private const KNOWN_DATACENTER_PROXY_SUBNETS = [
        '103.145.22.',
        '194.26.29.',
        '45.155.91.',
        '185.220.101.',
    ];

    /**
     * @var array<int, string>
     */
    private const BOT_USER_AGENT_SIGNATURES = [
        'HeadlessChrome',
        'Puppeteer',
        'Playwright',
        'Selenium',
        'PhantomJS',
        'python-requests',
    ];

    /**
     * Analisis intelijen ancaman IP dan User Agent calon pembeli sekunder.
     *
     * @return array{
     *   is_threat_detected: bool,
     *   is_datacenter_proxy: bool,
     *   is_headless_crawler: bool,
     *   is_previously_blacklisted: bool,
     *   threat_summary: string
     * }
     */
    public function inspectThreat(int $userId, ?string $ipAddress, ?string $userAgent): array
    {
        $isDatacenter = false;
        $isHeadless = false;

        if ($ipAddress !== null) {
            foreach (self::KNOWN_DATACENTER_PROXY_SUBNETS as $subnet) {
                if (str_starts_with($ipAddress, $subnet)) {
                    $isDatacenter = true;
                    break;
                }
            }
        }

        if ($userAgent !== null) {
            foreach (self::BOT_USER_AGENT_SIGNATURES as $botSig) {
                if (stripos($userAgent, $botSig) !== false) {
                    $isHeadless = true;
                    break;
                }
            }
        }

        $previouslyBlacklisted = ScalperBlacklistLog::query()
            ->where('user_id', $userId)
            ->exists();

        $isThreat = $isDatacenter || $isHeadless || $previouslyBlacklisted;

        $threatReasons = [];
        if ($isDatacenter) {
            $threatReasons[] = 'DATACENTER_IP_SUBNET';
        }
        if ($isHeadless) {
            $threatReasons[] = 'HEADLESS_AUTOMATION_CLIENT';
        }
        if ($previouslyBlacklisted) {
            $threatReasons[] = 'PRIOR_BLACKLIST_RECORD';
        }

        return [
            'is_threat_detected' => $isThreat,
            'is_datacenter_proxy' => $isDatacenter,
            'is_headless_crawler' => $isHeadless,
            'is_previously_blacklisted' => $previouslyBlacklisted,
            'threat_summary' => count($threatReasons) > 0 ? implode(';', $threatReasons) : 'CLEAN_TRAFFIC',
        ];
    }
}
