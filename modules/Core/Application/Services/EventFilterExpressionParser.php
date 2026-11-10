<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

/**
 * Pengurai Ekspresi Filter Event (EventFilterExpressionParser)
 *
 * Mengurai string ekspresi filter teks (misal: "currency == 'IDR' AND total >= 500000")
 * menjadi struktur pohon aturan (Abstract Syntax Tree / AST) yang kompatibel dengan SubscriptionRuleEvaluator.
 */
class EventFilterExpressionParser
{
    /**
     * Mengurai string ekspresi logika menjadi representasi array kondisi berstruktur.
     *
     * @return array<string, mixed>
     */
    public function parse(string $expression): array
    {
        $expression = trim($expression);
        if (empty($expression)) {
            return [];
        }

        // 1. Tangani disjungsi OR tingkat atas
        $orClauses = preg_split('/\s+OR\s+/i', $expression);
        if ($orClauses !== false && count($orClauses) > 1) {
            $conditions = [];
            foreach ($orClauses as $clause) {
                $conditions[] = $this->parse($clause);
            }

            return [
                'op' => 'OR',
                'conditions' => $conditions,
            ];
        }

        // 2. Tangani konjungsi AND tingkat atas
        $andClauses = preg_split('/\s+AND\s+/i', $expression);
        if ($andClauses !== false && count($andClauses) > 1) {
            $conditions = [];
            foreach ($andClauses as $clause) {
                $conditions[] = $this->parse($clause);
            }

            return [
                'op' => 'AND',
                'conditions' => $conditions,
            ];
        }

        // 3. Tangani negasi NOT
        if (str_starts_with(strtoupper($expression), 'NOT ')) {
            $subExpr = substr($expression, 4);

            return [
                'op' => 'NOT',
                'conditions' => [$this->parse($subExpr)],
            ];
        }

        // 4. Daun kondisi tunggal: <field> <operator> <value>
        return $this->parseLeafCondition($expression);
    }

    /**
     * Mengurai satu klausa kondisi perbandingan tunggal.
     *
     * @return array{field: string, operator: string, value: mixed}
     */
    protected function parseLeafCondition(string $clause): array
    {
        $clause = trim($clause);

        // Pola regex mencocokkan: field, operator (==, !=, >=, <=, >, <, CONTAINS, PREFIX, IN), value
        $pattern = '/^([a-zA-Z0-9_\.\-]+)\s*(==|!=|>=|<=|>|<|CONTAINS|PREFIX|IN)\s*(.*)$/i';

        if (! preg_match($pattern, $clause, $matches)) {
            // Jika format tidak dikenali, anggap kondisi kesetaraan boolean sederhana
            return [
                'field' => $clause,
                'operator' => 'EXISTS',
                'value' => true,
            ];
        }

        $field = trim($matches[1]);
        $rawOp = strtoupper(trim($matches[2]));
        $rawValue = trim($matches[3]);

        $operator = match ($rawOp) {
            '==' => 'EQ',
            '!=' => 'NEQ',
            '>=' => 'GTE',
            '<=' => 'LTE',
            '>' => 'GT',
            '<' => 'LT',
            default => $rawOp,
        };

        $value = $this->normalizeValue($rawValue, $operator);

        return [
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
        ];
    }

    /**
     * Mengubah string representasi nilai menjadi tipe data asli (integer, float, boolean, array).
     */
    protected function normalizeValue(string $raw, string $operator): mixed
    {
        // Tangani array untuk operator IN: misal ('IDR', 'USD') atau [1, 2, 3]
        if ($operator === 'IN' || (str_starts_with($raw, '(') && str_ends_with($raw, ')')) || (str_starts_with($raw, '[') && str_ends_with($raw, ']'))) {
            $inner = trim($raw, '()[]');
            $items = explode(',', $inner);

            return array_map(fn ($item) => $this->normalizeValue(trim($item), 'EQ'), $items);
        }

        // String berpetik tunggal atau ganda: 'IDR' atau "PAID"
        if ((str_starts_with($raw, "'") && str_ends_with($raw, "'")) || (str_starts_with($raw, '"') && str_ends_with($raw, '"'))) {
            return substr($raw, 1, -1);
        }

        // Boolean: true, false
        if (strtolower($raw) === 'true') {
            return true;
        }
        if (strtolower($raw) === 'false') {
            return false;
        }

        // Null: null
        if (strtolower($raw) === 'null') {
            return null;
        }

        // Numerik: integer atau float
        if (is_numeric($raw)) {
            return str_contains($raw, '.') ? (float) $raw : (int) $raw;
        }

        return $raw;
    }
}
