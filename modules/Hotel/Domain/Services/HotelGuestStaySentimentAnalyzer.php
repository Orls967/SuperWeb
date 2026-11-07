<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

class HotelGuestStaySentimentAnalyzer
{
    /**
     * @var list<string>
     */
    private const NEGATIVE_KEYWORDS = [
        'noisy', 'bising', 'kotor', 'dirty', 'lambat', 'slow', 'cold food', 'dingin',
        'broken', 'rusak', 'unfriendly', 'kasar', 'kecewa', 'disappointed',
    ];

    /**
     * @var list<string>
     */
    private const POSITIVE_KEYWORDS = [
        'excellent', 'luar biasa', 'ramah', 'friendly', 'clean', 'bersih',
        'comfortable', 'nyaman', 'delicious', 'enak', 'fast', 'cepat', 'perfect',
    ];

    /**
     * Menganalisis sentimen ulasan atau catatan keluhan tamu untuk pemeringkatan indeks kepuasan.
     *
     * @return array{sentiment_score: float, sentiment_label: string, detected_positive_tokens: list<string>, detected_negative_tokens: list<string>, gm_escalation_needed: bool}
     */
    public function analyzeStayFeedback(string $feedbackText): array
    {
        $normalized = strtolower($feedbackText);

        $posTokens = [];
        foreach (self::POSITIVE_KEYWORDS as $word) {
            if (str_contains($normalized, $word)) {
                $posTokens[] = $word;
            }
        }

        $negTokens = [];
        foreach (self::NEGATIVE_KEYWORDS as $word) {
            if (str_contains($normalized, $word)) {
                $negTokens[] = $word;
            }
        }

        $posCount = count($posTokens);
        $negCount = count($negTokens);
        $total = $posCount + $negCount;

        if ($total === 0) {
            return [
                'sentiment_score' => 0.0,
                'sentiment_label' => 'NEUTRAL',
                'detected_positive_tokens' => [],
                'detected_negative_tokens' => [],
                'gm_escalation_needed' => false,
            ];
        }

        $score = round(($posCount - $negCount) / $total, 2);

        $label = 'NEUTRAL';
        if ($score >= 0.3) {
            $label = 'POSITIVE';
        } elseif ($score <= -0.3) {
            $label = 'CRITICAL_NEGATIVE';
        }

        $escalate = ($label === 'CRITICAL_NEGATIVE') || ($negCount >= 2);

        return [
            'sentiment_score' => $score,
            'sentiment_label' => $label,
            'detected_positive_tokens' => $posTokens,
            'detected_negative_tokens' => $negTokens,
            'gm_escalation_needed' => $escalate,
        ];
    }
}
