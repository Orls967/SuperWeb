<?php

declare(strict_types=1);

namespace App\Quality\TestHygiene;

use App\Quality\ArchScan\Rule;
use App\Quality\ArchScan\Violation;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\SourceFile;
use App\Quality\Support\Tokens;

/**
 * T2 — a test creates ledger accounts itself (X5, X23, K-11). The flow then passes
 * in the test but fails on a database seeded like production; tests must seed the
 * same chart of accounts as production.
 */
final class LedgerAccountInTestRule implements Rule
{
    private const CREATE_METHODS = ['create', 'firstorcreate', 'updateorcreate', 'forcecreate', 'insert', 'upsert'];

    public function id(): string
    {
        return 'T2';
    }

    public function description(): string
    {
        return 'Test membuat akun ledger sendiri (LedgerAccount::create|firstOrCreate|updateOrCreate, insert ke bank_ledger_accounts)';
    }

    public function check(SourceFile $file, ModuleRegistry $registry): array
    {
        if (! $file->isTest()) {
            return [];
        }

        $tokens = $file->tokens();
        $violations = [];

        foreach ($tokens as $index => $token) {
            $signature = null;

            foreach (self::CREATE_METHODS as $method) {
                if (Tokens::isStaticCall($tokens, $index, ['LedgerAccount'], $method)) {
                    $signature = 'LedgerAccount::'.$tokens[$index + 2]->text.'()';
                }
            }

            if (Tokens::isStaticCall($tokens, $index, ['DB'], 'table') && Tokens::stringLiteral($tokens[$index + 4] ?? $token) === 'bank_ledger_accounts') {
                $close = Tokens::matchingClose($tokens, $index + 3);
                $method = $close === null ? '' : strtolower($tokens[$close + 2]->text ?? '');

                if (($tokens[$close + 1] ?? null)?->is(T_OBJECT_OPERATOR) && in_array($method, ['insert', 'insertorignore', 'upsert'], true)) {
                    $signature = "DB::table('bank_ledger_accounts')->{$tokens[$close + 2]->text}()";
                }
            }

            if ($signature !== null) {
                $violations[] = new Violation($this->id(), $file->relativePath, $token->line, $signature, 'Akun ledger dibuat di test; seed chart of accounts produksi (KONSEP §A2.3, §A15.2).');
            }
        }

        return $violations;
    }
}
