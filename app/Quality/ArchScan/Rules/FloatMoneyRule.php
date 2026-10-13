<?php

declare(strict_types=1);

namespace App\Quality\ArchScan\Rules;

use App\Quality\ArchScan\Rule;
use App\Quality\ArchScan\Violation;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\Declarations;
use App\Quality\Support\SourceFile;
use App\Quality\Support\Tokens;

/**
 * A3 — money typed as float (parameters, properties, float/double columns) and
 * IDR stored as decimal (X3, K-14). Physical quantities (kWh, kg, hours, …) and
 * ratios are not money and are ignored.
 */
final class FloatMoneyRule implements Rule
{
    private const MONEY_WORDS = ['idr', 'amount', 'price', 'cost', 'fee', 'revenue', 'salary', 'total', 'budget', 'balance'];

    private const NOT_MONEY_WORDS = [
        'rate', 'ratio', 'percent', 'percentage', 'pct', 'factor', 'multiplier', 'score', 'probability',
        'qty', 'quantity', 'count', 'units', 'weight', 'volume', 'distance', 'capacity', 'density',
        'kg', 'kwh', 'mwh', 'wh', 'kw', 'mw', 'km', 'meter', 'meters', 'liter', 'liters', 'litre', 'ton', 'tons', 'tonnes',
        'hour', 'hours', 'jam', 'minute', 'minutes', 'day', 'days', 'month', 'months', 'year', 'years',
        'ms', 'millis', 'milliseconds', 'second', 'seconds', 'sec', 'secs', 'duration', 'latency', 'p50', 'p95', 'p99', 'percentile',
        'cpu', 'memory', 'mb', 'gb', 'bytes',
        'temperature', 'celsius', 'lat', 'lng', 'latitude', 'longitude',
    ];

    private const COLUMN_METHODS = ['decimal', 'unsigneddecimal', 'float', 'double'];

    public function id(): string
    {
        return 'A3';
    }

    public function description(): string
    {
        return 'Uang bertipe float (parameter/properti/kolom) atau kolom IDR decimal';
    }

    public function check(SourceFile $file, ModuleRegistry $registry): array
    {
        if ($file->module() === null) {
            return [];
        }

        $violations = [];

        foreach (Declarations::parameters($file) as $parameter) {
            if (in_array('float', $parameter['types'], true) && self::isMoneyName($parameter['name'])) {
                $violations[] = new Violation(
                    $this->id(),
                    $file->relativePath,
                    $parameter['line'],
                    "param float \${$parameter['name']} @ {$parameter['function']}()",
                    "Parameter uang \${$parameter['name']} bertipe float; pakai int minor unit (KONSEP §A2.6).",
                );
            }
        }

        foreach (Declarations::properties($file) as $property) {
            if (in_array('float', $property['types'], true) && self::isMoneyName($property['name'])) {
                $violations[] = new Violation(
                    $this->id(),
                    $file->relativePath,
                    $property['line'],
                    "property float \${$property['name']}",
                    "Properti uang \${$property['name']} bertipe float; pakai int minor unit (KONSEP §A2.6).",
                );
            }
        }

        if ($file->isMigration()) {
            $violations = [...$violations, ...$this->columnViolations($file)];
        }

        return $violations;
    }

    /**
     * `amountIdr`, `unitPrice`, `costPerDay` are money; `totalKwh`, `feeRate`, `totalHours` are not.
     * An explicit `idr` always means money; words after `per` describe the unit, not the value.
     */
    public static function isMoneyName(string $name): bool
    {
        $words = self::words($name);

        if (in_array('idr', $words, true)) {
            return true;
        }

        $per = array_search('per', $words, true);

        if ($per !== false) {
            $words = array_slice($words, 0, $per);
        }

        return array_intersect($words, self::MONEY_WORDS) !== []
            && array_intersect($words, self::NOT_MONEY_WORDS) === [];
    }

    /**
     * @return list<string>
     */
    private static function words(string $name): array
    {
        $snake = strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $name));

        return array_values(array_filter(explode('_', $snake), static fn (string $word): bool => $word !== ''));
    }

    /**
     * @return list<Violation>
     */
    private function columnViolations(SourceFile $file): array
    {
        $tokens = $file->tokens();
        $violations = [];

        foreach ($tokens as $index => $token) {
            if (! $token->is(T_OBJECT_OPERATOR) || ! isset($tokens[$index + 3])) {
                continue;
            }

            $method = strtolower($tokens[$index + 1]->text);

            if (! in_array($method, self::COLUMN_METHODS, true) || $tokens[$index + 2]->text !== '(') {
                continue;
            }

            $column = Tokens::stringLiteral($tokens[$index + 3]);

            if ($column === null) {
                continue;
            }

            $isIdrDecimal = str_contains($method, 'decimal') && in_array('idr', self::words($column), true);
            $isFloatMoney = in_array($method, ['float', 'double'], true) && self::isMoneyName($column);

            if ($isIdrDecimal || $isFloatMoney) {
                $violations[] = new Violation(
                    $this->id(),
                    $file->relativePath,
                    $token->line,
                    "column {$method}('{$column}')",
                    "Kolom uang '{$column}' memakai {$method}; IDR wajib bigInteger minor unit (KONSEP §A2.6).",
                );
            }
        }

        return $violations;
    }
}
