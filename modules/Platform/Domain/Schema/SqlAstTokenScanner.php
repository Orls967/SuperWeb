<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Schema;

final class SqlAstTokenScanner
{
    /**
     * Tokenizes raw DDL SQL into semantic keywords to detect dangerous lock-prone clauses.
     *
     * @return list<array{type: string, value: string, line: int}>
     */
    public function scanTokens(string $sql): array
    {
        $tokens = [];
        $lines = explode("\n", $sql);

        foreach ($lines as $lineNum => $line) {
            $words = preg_split('/\s+/', trim($line));
            if (! is_array($words)) {
                continue;
            }

            foreach ($words as $word) {
                if ($word === '') {
                    continue;
                }
                $upper = strtoupper(trim($word, ';,()'));
                $tokens[] = [
                    'type' => $this->classifyToken($upper),
                    'value' => $upper,
                    'line' => $lineNum + 1,
                ];
            }
        }

        return $tokens;
    }

    private function classifyToken(string $token): string
    {
        $keywords = ['ALTER', 'TABLE', 'ADD', 'DROP', 'COLUMN', 'INDEX', 'PARTITION', 'LOCK', 'ALGORITHM'];
        if (in_array($token, $keywords, true)) {
            return 'KEYWORD';
        }

        $dangerous = ['EXCLUSIVE', 'SHARED', 'COPY', 'FOREIGN', 'REFERENCES'];
        if (in_array($token, $dangerous, true)) {
            return 'RISK_KEYWORD';
        }

        return 'IDENTIFIER';
    }
}
