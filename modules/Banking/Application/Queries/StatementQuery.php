<?php

declare(strict_types=1);

namespace Modules\Banking\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerEntry;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatementQuery
{
    public function query(
        LedgerAccount $account,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $type = null,
    ): Builder {
        $query = LedgerEntry::with(['transaction.creator', 'transaction.reference'])
            ->where('account_id', $account->id);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate.' 00:00:00');
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate.' 23:59:59');
        }

        if ($type) {
            $query->whereHas('transaction', function (Builder $q) use ($type) {
                $q->where('type', $type);
            });
        }

        return $query->orderBy('id', 'desc');
    }

    public function paginate(
        LedgerAccount $account,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $type = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->query($account, $startDate, $endDate, $type)->paginate($perPage)->withQueryString();
    }

    public function exportCsv(
        LedgerAccount $account,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $type = null,
    ): StreamedResponse {
        $filename = 'mutasi-'.$account->code.'-'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($account, $startDate, $endDate, $type) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['ID Entri', 'Tanggal & Waktu', 'Tipe Transaksi', 'Deskripsi', 'Jumlah', 'Saldo Setelah', 'Kode Aset', 'ID Transaksi']);

            $this->query($account, $startDate, $endDate, $type)->chunk(200, function ($entries) use ($handle) {
                foreach ($entries as $entry) {
                    fputcsv($handle, [
                        $entry->id,
                        $entry->created_at->format('Y-m-d H:i:s'),
                        $entry->transaction->type ?? '-',
                        $entry->transaction->description ?? '-',
                        $entry->amount,
                        $entry->balance_after,
                        $entry->asset_code,
                        $entry->transaction->uuid ?? '-',
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
