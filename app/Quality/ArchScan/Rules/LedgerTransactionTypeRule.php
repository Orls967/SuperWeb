<?php

declare(strict_types=1);

namespace App\Quality\ArchScan\Rules;

use App\Quality\ArchScan\Rule;
use App\Quality\ArchScan\Violation;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\NameReferences;
use App\Quality\Support\SourceFile;
use App\Quality\Support\Tokens;
use BackedEnum;
use Modules\Banking\Domain\Enums\TransactionType;
use PhpToken;

/**
 * A5 — ledger posting `type` longer than the 32-character column, or not taken
 * from a transaction-type registry enum (K-19). Long types pass on SQLite but
 * fail on MySQL/PostgreSQL; free-text types cannot be reported or audited.
 */
final class LedgerTransactionTypeRule implements Rule
{
    public const MAX_LENGTH = 32;

    /** @var list<string> */
    private array $registeredTypes;

    /**
     * @param  list<string>|null  $registeredTypes  defaults to the values of Banking's TransactionType enum
     */
    public function __construct(?array $registeredTypes = null)
    {
        $this->registeredTypes = $registeredTypes ?? array_map(
            static fn (TransactionType $type): string => $type->value,
            TransactionType::cases(),
        );
    }

    public function id(): string
    {
        return 'A5';
    }

    public function description(): string
    {
        return 'Tipe posting ledger > 32 karakter atau tidak berasal dari registry enum tipe transaksi';
    }

    public function check(SourceFile $file, ModuleRegistry $registry): array
    {
        if ($file->module() === null) {
            return [];
        }

        $tokens = $file->tokens();
        $violations = [];

        foreach ($tokens as $index => $token) {
            if (! $token->is(T_NEW) || ! isset($tokens[$index + 2]) || ! Tokens::isNameToken($tokens[$index + 1])
                || Tokens::shortName($tokens[$index + 1]) !== 'PostingDTO' || $tokens[$index + 2]->text !== '(') {
                continue;
            }

            $close = Tokens::matchingClose($tokens, $index + 2);
            $expression = $close === null ? null : $this->typeExpression($tokens, $index + 2, $close);

            if ($expression === null) {
                continue;
            }

            $problem = $this->problem($file, $tokens, $expression[0], $expression[1]);

            if ($problem === null) {
                continue;
            }

            $violations[] = new Violation(
                $this->id(),
                $file->relativePath,
                $token->line,
                'PostingDTO type: '.Tokens::text($tokens, $expression[0], $expression[1] - 1),
                $problem,
            );
        }

        return $violations;
    }

    /**
     * Token range [start, end) of the `type` argument (named or first positional).
     *
     * @param  list<PhpToken>  $tokens
     * @return array{int, int}|null
     */
    private function typeExpression(array $tokens, int $open, int $close): ?array
    {
        $start = $open + 1;
        $first = null;

        while ($start < $close) {
            $end = min(Tokens::expressionEnd($tokens, $start, [',']), $close);
            $isNamed = $tokens[$start]->is(T_STRING) && ($tokens[$start + 1]->text ?? '') === ':';

            if ($isNamed && $tokens[$start]->text === 'type') {
                return [$start + 2, $end];
            }

            if ($first === null && ! $isNamed) {
                $first = [$start, $end];
            }

            $start = $end + 1;
        }

        return $first;
    }

    /**
     * @param  list<PhpToken>  $tokens
     */
    private function problem(SourceFile $file, array $tokens, int $start, int $end): ?string
    {
        if ($end - $start === 1 && ($literal = Tokens::stringLiteral($tokens[$start])) !== null) {
            if (strlen($literal) > self::MAX_LENGTH) {
                return "Tipe '{$literal}' ".strlen($literal).' karakter (> '.self::MAX_LENGTH.'): gagal di MySQL/PostgreSQL.';
            }

            return in_array($literal, $this->registeredTypes, true)
                ? null
                : "Tipe '{$literal}' tidak terdaftar di registry enum tipe transaksi.";
        }

        $isEnumValue = $end - $start === 5
            && Tokens::isNameToken($tokens[$start])
            && str_ends_with(Tokens::shortName($tokens[$start]), 'TransactionType')
            && $tokens[$start + 1]->is(T_DOUBLE_COLON)
            && $tokens[$start + 3]->is(T_OBJECT_OPERATOR)
            && $tokens[$start + 4]->text === 'value';

        if (! $isEnumValue) {
            return 'Tipe posting dinamis/tidak berasal dari enum tipe transaksi; tidak bisa diverifikasi.';
        }

        $enumClass = NameReferences::resolve($file, $tokens[$start]->text);
        $case = $enumClass.'::'.$tokens[$start + 2]->text;

        if (enum_exists($enumClass) && defined($case)) {
            $value = constant($case);

            if ($value instanceof BackedEnum && strlen((string) $value->value) > self::MAX_LENGTH) {
                return "Nilai {$tokens[$start + 2]->text} = '{$value->value}' ".strlen((string) $value->value).' karakter (> '.self::MAX_LENGTH.').';
            }
        }

        return null;
    }
}
