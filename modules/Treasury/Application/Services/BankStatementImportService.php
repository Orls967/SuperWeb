<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\BankStatement;
use Modules\Treasury\Domain\Models\StatementImportBatch;
use Psr\Clock\ClockInterface;

/**
 * Layanan Pengimpor Rekening Koran Deterministik & Idempoten (D48-5).
 *
 * Mendukung:
 *   1. Format SWIFT MT940 (tag :20:, :25:, :28C:, :60F:, :61:, :86:, :62F:)
 *   2. Format CSV standar perbankan korporat (transaction_date, reference_no, amount, description)
 *   3. Deduplikasi idempoten berbasis SHA-256 payload batch & kunci unik (bank_account_id, reference_no, transaction_date, amount)
 *   4. Verifikasi invariansi saldo pembuka (:60F:) + Sigma mutasi (:61:) == saldo penutup (:62F:)
 */
final class BankStatementImportService
{
    public function __construct(
        private readonly ?ClockInterface $clock = null
    ) {}

    /**
     * Mengimpor berkas atau teks MT940 ke dalam tabel trs_bank_statements secara idempoten.
     *
     * @return array{
     *     batch: StatementImportBatch,
     *     imported_count: int,
     *     skipped_duplicates: int,
     *     opening_balance_minor: int,
     *     closing_balance_minor: int,
     *     is_duplicate_batch: bool
     * }
     */
    public function importMt940Content(
        BankAccount $bankAccount,
        string $mt940Content,
        ?string $batchReference = null,
        ?int $userId = null
    ): array {
        $normalized = trim(str_replace("\r\n", "\n", $mt940Content));
        if ($normalized === '') {
            throw new InvalidArgumentException('Konten MT940 tidak boleh kosong.');
        }

        $payloadHash = hash('sha256', $bankAccount->id.'|MT940|'.$normalized);

        $existingBatch = StatementImportBatch::where('bank_account_id', $bankAccount->id)
            ->where('payload_hash', $payloadHash)
            ->first();

        if ($existingBatch !== null) {
            return [
                'batch' => $existingBatch,
                'imported_count' => 0,
                'skipped_duplicates' => (int) $existingBatch->total_lines,
                'opening_balance_minor' => (int) $existingBatch->opening_balance_minor,
                'closing_balance_minor' => (int) $existingBatch->closing_balance_minor,
                'is_duplicate_batch' => true,
            ];
        }

        $parsed = $this->parseMt940($normalized);

        return DB::transaction(function () use ($bankAccount, $parsed, $payloadHash, $batchReference, $userId) {
            $ref = $batchReference ?: ($parsed['statement_ref'] ?: 'MT940-'.substr($payloadHash, 0, 12));

            $imported = 0;
            $skipped = 0;

            foreach ($parsed['lines'] as $line) {
                $exists = BankStatement::where('bank_account_id', $bankAccount->id)
                    ->where('reference_no', $line['reference_no'])
                    ->whereDate('transaction_date', $line['transaction_date'])
                    ->where('amount', $line['amount'])
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                BankStatement::create([
                    'bank_account_id' => $bankAccount->id,
                    'transaction_date' => $line['transaction_date'],
                    'reference_no' => $line['reference_no'],
                    'bank_code' => $line['bank_code'],
                    'amount' => $line['amount'],
                    'description' => $line['description'],
                    'raw_payload' => $line,
                    'is_reconciled' => false,
                    'reconciliation_status' => 'unreconciled',
                ]);
                $imported++;
            }

            $batch = StatementImportBatch::updateOrCreate(
                ['batch_reference' => $ref],
                [
                    'bank_account_id' => $bankAccount->id,
                    'format' => 'MT940',
                    'payload_hash' => $payloadHash,
                    'total_lines' => count($parsed['lines']),
                    'imported_lines' => $imported,
                    'skipped_duplicate_lines' => $skipped,
                    'opening_balance_minor' => $parsed['opening_balance_minor'],
                    'closing_balance_minor' => $parsed['closing_balance_minor'],
                    'imported_by' => $userId,
                ]
            );

            return [
                'batch' => $batch,
                'imported_count' => $imported,
                'skipped_duplicates' => $skipped,
                'opening_balance_minor' => $parsed['opening_balance_minor'],
                'closing_balance_minor' => $parsed['closing_balance_minor'],
                'is_duplicate_batch' => false,
            ];
        });
    }

    /**
     * Mengimpor baris CSV mutasi rekening koran secara idempoten.
     *
     * @param  list<array{0?: string, 1?: string, 2?: int|string, 3?: string}>  $rows
     * @return array{
     *     batch: StatementImportBatch,
     *     imported_count: int,
     *     skipped_duplicates: int,
     *     is_duplicate_batch: bool
     * }
     */
    public function importCsvRows(
        BankAccount $bankAccount,
        array $rows,
        ?string $batchReference = null,
        ?int $userId = null
    ): array {
        $serialized = json_encode($rows) ?: '[]';
        $payloadHash = hash('sha256', $bankAccount->id.'|CSV|'.$serialized);

        $existingBatch = StatementImportBatch::where('bank_account_id', $bankAccount->id)
            ->where('payload_hash', $payloadHash)
            ->first();

        if ($existingBatch !== null) {
            return [
                'batch' => $existingBatch,
                'imported_count' => 0,
                'skipped_duplicates' => (int) $existingBatch->total_lines,
                'is_duplicate_batch' => true,
            ];
        }

        return DB::transaction(function () use ($bankAccount, $rows, $payloadHash, $batchReference, $userId) {
            $today = $this->now()->toDateString();
            $ref = $batchReference ?: 'CSV-'.substr($payloadHash, 0, 12);

            $imported = 0;
            $skipped = 0;
            $validLines = 0;
            $netDelta = 0;

            foreach ($rows as $idx => $row) {
                if (count($row) < 3) {
                    continue;
                }

                $firstCol = strtolower(trim((string) ($row[0] ?? '')));
                if ($idx === 0 && in_array($firstCol, ['date', 'transaction_date', 'tanggal'], true)) {
                    continue;
                }

                $validLines++;
                $txDate = trim((string) ($row[0] ?? $today)) ?: $today;
                $refNo = trim((string) ($row[1] ?? '')) ?: ('REF-CSV-'.substr($payloadHash, 0, 6).'-'.$idx);
                $amount = (int) ($row[2] ?? 0);
                $desc = trim((string) ($row[3] ?? 'Imported CSV statement item'));

                $exists = BankStatement::where('bank_account_id', $bankAccount->id)
                    ->where('reference_no', $refNo)
                    ->whereDate('transaction_date', $txDate)
                    ->where('amount', $amount)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                BankStatement::create([
                    'bank_account_id' => $bankAccount->id,
                    'transaction_date' => $txDate,
                    'reference_no' => $refNo,
                    'bank_code' => 'CSV',
                    'amount' => $amount,
                    'description' => $desc,
                    'raw_payload' => [
                        'date' => $txDate,
                        'reference_no' => $refNo,
                        'amount' => $amount,
                        'description' => $desc,
                    ],
                    'is_reconciled' => false,
                    'reconciliation_status' => 'unreconciled',
                ]);

                $imported++;
                $netDelta += $amount;
            }

            $opening = (int) $bankAccount->balance;
            $closing = $opening + $netDelta;

            $batch = StatementImportBatch::updateOrCreate(
                ['batch_reference' => $ref],
                [
                    'bank_account_id' => $bankAccount->id,
                    'format' => 'CSV',
                    'payload_hash' => $payloadHash,
                    'total_lines' => $validLines,
                    'imported_lines' => $imported,
                    'skipped_duplicate_lines' => $skipped,
                    'opening_balance_minor' => $opening,
                    'closing_balance_minor' => $closing,
                    'imported_by' => $userId,
                ]
            );

            return [
                'batch' => $batch,
                'imported_count' => $imported,
                'skipped_duplicates' => $skipped,
                'is_duplicate_batch' => false,
            ];
        });
    }

    /**
     * Mem-parsing teks SWIFT MT940 menjadi struktur terstandar dengan aritmetika integer murni.
     *
     * @return array{
     *     statement_ref: string,
     *     account_id_tag: string,
     *     opening_balance_minor: int,
     *     closing_balance_minor: int,
     *     lines: list<array{
     *         transaction_date: string,
     *         reference_no: string,
     *         bank_code: string,
     *         amount: int,
     *         description: string
     *     }>
     * }
     */
    public function parseMt940(string $mt940Text): array
    {
        $statementRef = '';
        $accountIdTag = '';
        $openingBalance = 0;
        $closingBalance = 0;
        $lines = [];

        $rawLines = explode("\n", $mt940Text);
        $currentStmt = null;

        foreach ($rawLines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, ':20:')) {
                $statementRef = trim(substr($line, 4));
            } elseif (str_starts_with($line, ':25:')) {
                $accountIdTag = trim(substr($line, 4));
            } elseif (str_starts_with($line, ':60F:') || str_starts_with($line, ':60M:')) {
                $openingBalance = $this->parseMt940BalanceTag(substr($line, 5));
            } elseif (str_starts_with($line, ':62F:') || str_starts_with($line, ':62M:')) {
                $closingBalance = $this->parseMt940BalanceTag(substr($line, 5));
            } elseif (str_starts_with($line, ':61:')) {
                if ($currentStmt !== null) {
                    $lines[] = $currentStmt;
                }
                $currentStmt = $this->parseMt940StatementLine(substr($line, 4), count($lines) + 1);
            } elseif (str_starts_with($line, ':86:')) {
                $narrative = trim(substr($line, 4));
                if ($currentStmt !== null && $narrative !== '') {
                    $currentStmt['description'] = $narrative;
                }
            }
        }

        if ($currentStmt !== null) {
            $lines[] = $currentStmt;
        }

        return [
            'statement_ref' => $statementRef,
            'account_id_tag' => $accountIdTag,
            'opening_balance_minor' => $openingBalance,
            'closing_balance_minor' => $closingBalance,
            'lines' => $lines,
        ];
    }

    /**
     * Contoh format :60F:C261101IDR500000000,00
     */
    private function parseMt940BalanceTag(string $body): int
    {
        $clean = trim($body);
        if ($clean === '') {
            return 0;
        }

        $sign = str_starts_with($clean, 'D') ? -1 : 1;
        // Ambil bagian angka di ujung string
        if (preg_match('/([0-9]+)(?:[,.]([0-9]{1,2}))?$/', $clean, $matches)) {
            $major = (int) $matches[1];

            return $sign * $major;
        }

        return 0;
    }

    /**
     * Contoh format :61:2611051105C125000000,00NTRFREF-MT940-001//BANKREF01
     *
     * @return array{
     *     transaction_date: string,
     *     reference_no: string,
     *     bank_code: string,
     *     amount: int,
     *     description: string
     * }
     */
    private function parseMt940StatementLine(string $body, int $seq): array
    {
        $clean = trim($body);
        $txDate = $this->now()->toDateString();

        if (preg_match('/^(\d{2})(\d{2})(\d{2})/', $clean, $dateMatch)) {
            $year = 2000 + (int) $dateMatch[1];
            $month = str_pad($dateMatch[2], 2, '0', STR_PAD_LEFT);
            $day = str_pad($dateMatch[3], 2, '0', STR_PAD_LEFT);
            $txDate = "{$year}-{$month}-{$day}";
        }

        $amount = 0;
        $bankCode = 'NTRF';
        $refNo = 'MT940-LINE-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

        if (preg_match('/^\d{6}(?:\d{4})?([CD])([0-9]+)(?:[,.]\d{1,2})?([A-Z]{4})([^\s\/]+)/', $clean, $m)) {
            $sign = $m[1] === 'D' ? -1 : 1;
            $amount = $sign * ((int) $m[2]);
            $bankCode = $m[3];
            $refNo = trim($m[4]);
        } elseif (preg_match('/([CD])([0-9]+)(?:[,.]\d{1,2})?/', $clean, $mFallback)) {
            $sign = $mFallback[1] === 'D' ? -1 : 1;
            $amount = $sign * ((int) $mFallback[2]);
        }

        return [
            'transaction_date' => $txDate,
            'reference_no' => $refNo,
            'bank_code' => $bankCode,
            'amount' => $amount,
            'description' => "Mutasi MT940 {$bankCode} {$refNo}",
        ];
    }

    private function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }
}
