<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\DesignSystem;

use Modules\Core\Domain\Models\DsToken;
use Modules\Shared\Application\Algorithms\Ui\WcagContrastCalculator;

/**
 * Penguji dan penerbit sertifikat kepatuhan kontras token warna (WCAG 2.1 Level AA & AAA).
 */
class TokenContrastCertifier
{
    /**
     * Rasio kontras minimum WCAG 2.1 dalam basis points (bps, 1:1 = 100 bps).
     */
    public const WCAG_AA_NORMAL_TEXT_BPS = 450; // 4.5:1

    public const WCAG_AA_LARGE_TEXT_BPS = 300;  // 3.0:1

    public const WCAG_AAA_NORMAL_TEXT_BPS = 700; // 7.0:1

    /**
     * Jalankan sertifikasi batch kontras seluruh token warna terdaftar.
     *
     * @param  string|null  $themeFilter  Batasi tema tertentu ('light', 'dark', 'high-contrast', atau null untuk semua)
     * @return array{certified: bool, total_pairs_checked: int, passed_pairs: int, failed_pairs: int, failures: array<int, array<string, mixed>>, certificate_seal: string, generated_at: string}
     */
    public function certifyAllTokens(?string $themeFilter = null): array
    {
        $query = DsToken::query();
        if ($themeFilter !== null) {
            $query->where('theme', $themeFilter);
        }

        $tokens = $query->get();
        $tokensByTheme = [];

        foreach ($tokens as $token) {
            $tokensByTheme[$token->theme][$token->name] = $token->value;
        }

        $failures = [];
        $totalChecked = 0;
        $passedCount = 0;

        foreach ($tokensByTheme as $theme => $themeTokens) {
            $pairs = $this->buildSemanticPairsForTheme($themeTokens);

            foreach ($pairs as $pair) {
                $totalChecked++;
                $fgName = $pair['fg_name'];
                $bgName = $pair['bg_name'];
                $fgHex = $pair['fg_hex'];
                $bgHex = $pair['bg_hex'];
                $requiredBps = $pair['required_bps'] ?? self::WCAG_AA_NORMAL_TEXT_BPS;

                try {
                    $result = WcagContrastCalculator::calculate($fgHex, $bgHex);
                    $actualBps = (int) $result['ratio_bps'];

                    if ($actualBps < $requiredBps) {
                        $failures[] = [
                            'theme' => $theme,
                            'fg_token' => $fgName,
                            'fg_value' => $fgHex,
                            'bg_token' => $bgName,
                            'bg_value' => $bgHex,
                            'actual_ratio' => $actualBps / 100.0,
                            'required_ratio' => $requiredBps / 100.0,
                            'deficit_ratio' => round(($requiredBps - $actualBps) / 100.0, 2),
                            'severity' => 'critical',
                        ];
                    } else {
                        $passedCount++;
                    }
                } catch (\Throwable $e) {
                    $failures[] = [
                        'theme' => $theme,
                        'fg_token' => $fgName,
                        'bg_token' => $bgName,
                        'error' => $e->getMessage(),
                        'severity' => 'error',
                    ];
                }
            }
        }

        $certified = count($failures) === 0 && $totalChecked > 0;
        $now = date('Y-m-d H:i:s');

        $sealData = [
            'certified' => $certified,
            'total_pairs' => $totalChecked,
            'passed' => $passedCount,
            'timestamp' => $now,
            'filter' => $themeFilter ?? 'all',
        ];
        $certificateSeal = hash('sha256', json_encode($sealData, JSON_THROW_ON_ERROR));

        return [
            'certified' => $certified,
            'total_pairs_checked' => $totalChecked,
            'passed_pairs' => $passedCount,
            'failed_pairs' => count($failures),
            'failures' => $failures,
            'certificate_seal' => $certificateSeal,
            'generated_at' => $now,
        ];
    }

    /**
     * Bangun pasangan semantik foreground vs background yang wajib memiliki kontras memadai.
     *
     * @param  array<string, string>  $tokens
     * @return array<int, array{fg_name: string, fg_hex: string, bg_name: string, bg_hex: string, required_bps: int}>
     */
    protected function buildSemanticPairsForTheme(array $tokens): array
    {
        $pairs = [];

        // Pasangan standar kanonikal:
        // 1. Teks teks normal terhadap background
        if (isset($tokens['text-primary'], $tokens['bg-primary'])) {
            $pairs[] = [
                'fg_name' => 'text-primary',
                'fg_hex' => $tokens['text-primary'],
                'bg_name' => 'bg-primary',
                'bg_hex' => $tokens['bg-primary'],
                'required_bps' => self::WCAG_AA_NORMAL_TEXT_BPS,
            ];
        }

        // 2. Teks sekunder terhadap background
        if (isset($tokens['text-secondary'], $tokens['bg-primary'])) {
            $pairs[] = [
                'fg_name' => 'text-secondary',
                'fg_hex' => $tokens['text-secondary'],
                'bg_name' => 'bg-primary',
                'bg_hex' => $tokens['bg-primary'],
                'required_bps' => self::WCAG_AA_LARGE_TEXT_BPS,
            ];
        }

        // 3. Teks terhadap surface/card
        if (isset($tokens['text-primary'], $tokens['bg-surface'])) {
            $pairs[] = [
                'fg_name' => 'text-primary',
                'fg_hex' => $tokens['text-primary'],
                'bg_name' => 'bg-surface',
                'bg_hex' => $tokens['bg-surface'],
                'required_bps' => self::WCAG_AA_NORMAL_TEXT_BPS,
            ];
        }

        // 4. Action/button primary teks terhadap warna tombol
        if (isset($tokens['action-primary-contrast'], $tokens['action-primary'])) {
            $pairs[] = [
                'fg_name' => 'action-primary-contrast',
                'fg_hex' => $tokens['action-primary-contrast'],
                'bg_name' => 'action-primary',
                'bg_hex' => $tokens['action-primary'],
                'required_bps' => self::WCAG_AA_NORMAL_TEXT_BPS,
            ];
        }

        // 5. Danger teks terhadap action-danger
        if (isset($tokens['action-danger-contrast'], $tokens['action-danger'])) {
            $pairs[] = [
                'fg_name' => 'action-danger-contrast',
                'fg_hex' => $tokens['action-danger-contrast'],
                'bg_name' => 'action-danger',
                'bg_hex' => $tokens['action-danger'],
                'required_bps' => self::WCAG_AA_NORMAL_TEXT_BPS,
            ];
        }

        // 6. Border terhadap background (WCAG 1.4.11 Non-text contrast >= 3:1)
        if (isset($tokens['border-subtle'], $tokens['bg-primary'])) {
            $pairs[] = [
                'fg_name' => 'border-subtle',
                'fg_hex' => $tokens['border-subtle'],
                'bg_name' => 'bg-primary',
                'bg_hex' => $tokens['bg-primary'],
                'required_bps' => 120, // baseline subtle border
            ];
        }

        return $pairs;
    }
}
