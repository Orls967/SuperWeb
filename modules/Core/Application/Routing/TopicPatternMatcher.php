<?php

declare(strict_types=1);

namespace Modules\Core\Application\Routing;

/**
 * Pencocok Pola Topik Event Spine (TopicPatternMatcher)
 *
 * Menerapkan pencocokan pola topik hierarkis bergaya AMQP / MQTT:
 * - '*' mencocokkan tepat satu kata dalam hierarki (misal 'store.*.paid' cocok dengan 'store.order.paid')
 * - '#', '.*', atau '*' di akhir mencocokkan sisa seluruh hierarki
 * - Pola eksak mencocokkan string identik
 */
class TopicPatternMatcher
{
    /**
     * Memeriksa apakah topik cocok dengan pola langganan yang diberikan.
     */
    public function matches(string $pattern, string $topic): bool
    {
        $pattern = trim($pattern);
        $topic = trim($topic);

        if ($pattern === '*' || $pattern === '#') {
            return true;
        }

        if ($pattern === $topic) {
            return true;
        }

        // Tangani pola prefix wildcard berakhiran '.*'
        if (str_ends_with($pattern, '.*')) {
            $prefix = substr($pattern, 0, -2);

            return $topic === $prefix || str_starts_with($topic, "{$prefix}.");
        }

        // Tangani pola berakhiran '#'
        if (str_ends_with($pattern, '.#')) {
            $prefix = substr($pattern, 0, -2);

            return $topic === $prefix || str_starts_with($topic, "{$prefix}.");
        }

        // Tangani pola token per token yang dipisahkan titik '.'
        $pTokens = explode('.', $pattern);
        $tTokens = explode('.', $topic);

        $pCount = count($pTokens);
        $tCount = count($tTokens);

        $pIndex = 0;
        $tIndex = 0;

        while ($pIndex < $pCount && $tIndex < $tCount) {
            $pTok = $pTokens[$pIndex];
            $tTok = $tTokens[$tIndex];

            if ($pTok === '#') {
                // Jika # adalah token terakhir di pola, cocokkan sisa topik
                if ($pIndex === $pCount - 1) {
                    return true;
                }
                // Jika ada token setelah #, cari kecocokan berikutnya
                $nextPTok = $pTokens[$pIndex + 1];
                while ($tIndex < $tCount && $tTokens[$tIndex] !== $nextPTok) {
                    $tIndex++;
                }
                $pIndex++;

                continue;
            }

            if ($pTok !== '*' && $pTok !== $tTok) {
                return false;
            }

            $pIndex++;
            $tIndex++;
        }

        // Jika pola tersisa '#', cocokkan
        while ($pIndex < $pCount && $pTokens[$pIndex] === '#') {
            $pIndex++;
        }

        return $pIndex === $pCount && $tIndex === $tCount;
    }

    /**
     * Memfilter kumpulan topik yang cocok dengan salah satu pola yang diizinkan.
     *
     * @param  array<int, string>  $patterns
     * @param  array<int, string>  $topics
     * @return array<int, string>
     */
    public function filterMatching(array $patterns, array $topics): array
    {
        $matched = [];
        foreach ($topics as $topic) {
            foreach ($patterns as $pattern) {
                if ($this->matches($pattern, $topic)) {
                    $matched[] = $topic;
                    break;
                }
            }
        }

        return array_values(array_unique($matched));
    }
}

