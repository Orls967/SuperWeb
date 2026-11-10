<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Ui;

use RuntimeException;

/**
 * ThemeTokenResolver (Algoritma A2 — Fase 240)
 *
 * Menyelesaikan pohon hierarki token tema tri-state (hc <- dark <- light)
 * dan alias token {nama} pada tema peminta, mendeteksi siklus dependensi (TOKEN_CYCLE),
 * dan memvalidasi pasangan kontras semantik via Algoritma A1 WcagContrastCalculator.
 *
 * Kompleksitas Waktu: O(K * D) di mana K = total token, D <= 3 kedalaman rantai warisan.
 * Kompleksitas Ruang: O(K)
 */
class ThemeTokenResolver
{
    public const STATUS_RESOLVED = 'RESOLVED';
    public const STATUS_CYCLE = 'TOKEN_CYCLE';
    public const STATUS_UNDEFINED = 'TOKEN_UNDEFINED';

    /**
     * @var array<string, list<string>> Rantai pewarisan tema (tema -> induk -> ... -> light)
     */
    private const INHERITANCE_CHAINS = [
        'light' => ['light'],
        'dark' => ['dark', 'light'],
        'hc' => ['hc', 'dark', 'light'],
    ];

    public function __construct(
        private readonly ?WcagContrastCalculator $contrastCalculator = null
    ) {}

    /**
     * Menyelesaikan seluruh token untuk tema tertentu.
     *
     * @param array<string, array<string, string>> $themeDefinitions [tema => [token_name => value]]
     * @param string $requestingTheme 'light' | 'dark' | 'hc'
     * @return array{
     *   theme: string,
     *   status: string,
     *   tokens: array<string, string>,
     *   contrast_pairs: array<string, array{ratio_bps: int, passes_aa: bool}>,
     *   errors: list<string>,
     *   certificate: string
     * }
     */
    public function resolve(array $themeDefinitions, string $requestingTheme): array
    {
        $calculator = $this->contrastCalculator ?? new WcagContrastCalculator();
        $chain = self::INHERITANCE_CHAINS[$requestingTheme] ?? [$requestingTheme, 'light'];

        // Kumpulkan semua nama token yang ada di seluruh rantai
        $tokenNames = [];
        foreach ($chain as $theme) {
            if (isset($themeDefinitions[$theme])) {
                foreach (array_keys($themeDefinitions[$theme]) as $name) {
                    $tokenNames[$name] = true;
                }
            }
        }
        $sortedNames = array_keys($tokenNames);
        sort($sortedNames);

        $resolvedTokens = [];
        $errors = [];
        $globalStatus = self::STATUS_RESOLVED;

        foreach ($sortedNames as $name) {
            $visited = [];
            $res = $this->resolveSingleToken($name, $requestingTheme, $chain, $themeDefinitions, $visited);
            if ($res['status'] !== self::STATUS_RESOLVED) {
                $globalStatus = $res['status'];
                $errors[] = "Token '{$name}': {$res['status']}";
                $resolvedTokens[$name] = $res['status'];
            } else {
                $resolvedTokens[$name] = $res['value'];
            }
        }

        // Evaluasi pasangan kontras standar: text/bg, onPrimary/primary, muted/bg
        $contrastPairs = [];
        if ($globalStatus === self::STATUS_RESOLVED) {
            $standardPairs = [
                'text/bg' => ['text', 'bg'],
                'onPrimary/primary' => ['onPrimary', 'primary'],
                'muted/bg' => ['muted', 'bg'],
            ];

            foreach ($standardPairs as $pairKey => [$fgKey, $bgKey]) {
                if (isset($resolvedTokens[$fgKey], $resolvedTokens[$bgKey])) {
                    $fgVal = $resolvedTokens[$fgKey];
                    $bgVal = $resolvedTokens[$bgKey];
                    if (str_starts_with($fgVal, '#') && str_starts_with($bgVal, '#')) {
                        $calc = $calculator->calculate($fgVal, $bgVal);
                        $contrastPairs[$pairKey] = [
                            'ratio_bps' => $calc['ratio_bps'],
                            'passes_aa' => $calc['passes_aa_normal'],
                        ];
                    }
                }
            }
        }

        $payload = [
            'theme' => $requestingTheme,
            'status' => $globalStatus,
            'tokens' => $resolvedTokens,
            'contrast_pairs' => $contrastPairs,
            'errors' => $errors,
        ];

        $payload['certificate'] = TokenResolutionCertificateVerifier::sign($payload);

        return $payload;
    }

    /**
     * Menyelesaikan satu token secara rekursif dengan penelusuran rantai dan alias di tema peminta.
     *
     * @param list<string> $chain
     * @param array<string, array<string, string>> $themeDefinitions
     * @param array<string, bool> $visited
     * @return array{status: string, value: string}
     */
    private function resolveSingleToken(
        string $tokenName,
        string $requestingTheme,
        array $chain,
        array $themeDefinitions,
        array &$visited
    ): array {
        if (isset($visited[$tokenName])) {
            return ['status' => self::STATUS_CYCLE, 'value' => self::STATUS_CYCLE];
        }
        $visited[$tokenName] = true;

        // Cari definisi pertama di rantai pewarisan
        $rawVal = null;
        foreach ($chain as $theme) {
            if (isset($themeDefinitions[$theme][$tokenName])) {
                $rawVal = $themeDefinitions[$theme][$tokenName];
                break;
            }
        }

        if ($rawVal === null) {
            return ['status' => self::STATUS_UNDEFINED, 'value' => self::STATUS_UNDEFINED];
        }

        // Cek apakah berupa alias {nama}
        if (preg_match('/^\{([a-zA-Z0-9_.-]+)\}$/', trim($rawVal), $matches)) {
            $aliasTarget = $matches[1];
            // Alias diselesaikan DI TEMA PEMINTA
            return $this->resolveSingleToken(
                $aliasTarget,
                $requestingTheme,
                self::INHERITANCE_CHAINS[$requestingTheme] ?? [$requestingTheme, 'light'],
                $themeDefinitions,
                $visited
            );
        }

        return ['status' => self::STATUS_RESOLVED, 'value' => strtoupper(trim($rawVal))];
    }
}
