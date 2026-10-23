<?php

declare(strict_types=1);

namespace App\Quality\ArchScan\Rules;

use App\Quality\ArchScan\Rule;
use App\Quality\ArchScan\Violation;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\Declarations;
use App\Quality\Support\SourceFile;
use App\Quality\Support\Tokens;
use PhpToken;

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
        $remediated = $this->remediatedColumnsInModule($file);

        $downRanges = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i]->is(T_FUNCTION) && isset($tokens[$i + 1]) && strtolower($tokens[$i + 1]->text) === 'down') {
                for ($j = $i + 2; $j < $count; $j++) {
                    if ($tokens[$j]->text === '{') {
                        $close = Tokens::matchingClose($tokens, $j);
                        if ($close !== null) {
                            $downRanges[] = [$j, $close];
                        }
                        break;
                    }
                }
            }
        }

        foreach ($tokens as $index => $token) {
            foreach ($downRanges as [$start, $end]) {
                if ($index >= $start && $index <= $end) {
                    continue 2;
                }
            }

            if (! $token->is(T_OBJECT_OPERATOR) || ! isset($tokens[$index + 3])) {
                continue;
            }

            $method = strtolower($tokens[$index + 1]->text);

            if (! in_array($method, self::COLUMN_METHODS, true) || $tokens[$index + 2]->text !== '(') {
                continue;
            }

            $column = Tokens::stringLiteral($tokens[$index + 3]);

            if ($column === null || in_array($column, $remediated, true)) {
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

    /**
     * @return list<string>
     */
    private function remediatedColumnsInModule(SourceFile $file): array
    {
        $absPath = $file->absolutePath;
        if ($absPath === null || ! is_file($absPath)) {
            return [];
        }

        $dir = dirname($absPath);
        if (! is_dir($dir)) {
            return [];
        }

        $currentBaseName = basename($absPath);
        $files = glob($dir.'/*.php') ?: [];
        $remediated = [];

        $remediationMethods = ['biginteger', 'unsignedbiginteger', 'decimal', 'unsigneddecimal'];

        foreach ($files as $otherPath) {
            if (strcmp(basename($otherPath), $currentBaseName) <= 0) {
                continue;
            }

            $content = @file_get_contents($otherPath);
            if ($content === false) {
                continue;
            }

            $tokens = array_values(array_filter(
                PhpToken::tokenize($content),
                static fn (PhpToken $t): bool => ! $t->isIgnorable(),
            ));

            $count = count($tokens);
            for ($i = 0; $i < $count; $i++) {
                if (! $tokens[$i]->is(T_OBJECT_OPERATOR) || ! isset($tokens[$i + 3])) {
                    continue;
                }

                $method = strtolower($tokens[$i + 1]->text);
                if (! in_array($method, $remediationMethods, true) || $tokens[$i + 2]->text !== '(') {
                    continue;
                }

                $column = Tokens::stringLiteral($tokens[$i + 3]);
                if ($column === null) {
                    continue;
                }

                for ($j = $i + 4; $j < $count; $j++) {
                    if ($tokens[$j]->text === ';') {
                        break;
                    }

                    if ($tokens[$j]->is(T_OBJECT_OPERATOR) && isset($tokens[$j + 2])) {
                        if (strtolower($tokens[$j + 1]->text) === 'change' && $tokens[$j + 2]->text === '(') {
                            $remediated[$column] = true;
                            break;
                        }
                    }
                }
            }
        }

        return array_keys($remediated);
    }
}
