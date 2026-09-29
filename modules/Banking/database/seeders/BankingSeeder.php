<?php

declare(strict_types=1);

namespace Modules\Banking\database\seeders;

use Brick\Math\BigDecimal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;

class BankingSeeder extends Seeder
{
    public function run(): void
    {
        $systemAccounts = [
            // Clearing
            [
                'code' => 'clearing:external:IDR',
                'name' => 'Rekening Kliring Eksternal IDR',
                'asset_code' => 'IDR',
                'kind' => AccountKind::CLEARING->value,
                'allow_negative' => true,
            ],
            // Revenue
            [
                'code' => 'revenue:autoserve:service:IDR',
                'name' => 'Pendapatan Jasa Servis Bengkel',
                'asset_code' => 'IDR',
                'kind' => AccountKind::REVENUE->value,
                'allow_negative' => false,
            ],
            [
                'code' => 'revenue:autoserve:parts:IDR',
                'name' => 'Pendapatan Penjualan Sparepart Bengkel',
                'asset_code' => 'IDR',
                'kind' => AccountKind::REVENUE->value,
                'allow_negative' => false,
            ],
            [
                'code' => 'revenue:store:IDR',
                'name' => 'Pendapatan Toko & Merchandise',
                'asset_code' => 'IDR',
                'kind' => AccountKind::REVENUE->value,
                'allow_negative' => false,
            ],
            // Escrow
            [
                'code' => 'escrow:payment:IDR',
                'name' => 'Rekening Escrow Pembayaran',
                'asset_code' => 'IDR',
                'kind' => AccountKind::ESCROW->value,
                'allow_negative' => false,
            ],
            // Exchange IDR
            [
                'code' => 'exchange:IDR',
                'name' => 'Kas Likuiditas Exchange IDR',
                'asset_code' => 'IDR',
                'kind' => AccountKind::EXCHANGE->value,
                'allow_negative' => false,
            ],
            // Loan Receivable & Fee
            [
                // Saldo negatif = pokok pinjaman yang masih beredar di tangan peminjam
                'code' => 'loan_receivable:IDR',
                'name' => 'Piutang Pembiayaan HODL-to-Drive IDR',
                'asset_code' => 'IDR',
                'kind' => AccountKind::LOAN_RECEIVABLE->value,
                'allow_negative' => true,
            ],
            [
                'code' => 'fin:interest:IDR',
                'name' => 'Pendapatan Bunga Pembiayaan HODL-to-Drive',
                'asset_code' => 'IDR',
                'kind' => AccountKind::REVENUE->value,
                'allow_negative' => false,
            ],
            [
                'code' => 'fin:penalty:IDR',
                'name' => 'Pendapatan Denda Keterlambatan Cicilan',
                'asset_code' => 'IDR',
                'kind' => AccountKind::REVENUE->value,
                'allow_negative' => false,
            ],
            [
                'code' => 'fee:banking:IDR',
                'name' => 'Pendapatan Biaya Transfer & Transaksi Banking',
                'asset_code' => 'IDR',
                'kind' => AccountKind::FEE->value,
                'allow_negative' => false,
            ],
            // Beban operasional bengkel untuk pembelian sparepart backorder
            [
                'code' => 'expense:autoserve:parts:IDR',
                'name' => 'Beban Pembelian Sparepart Bengkel (Backorder)',
                'asset_code' => 'IDR',
                'kind' => AccountKind::EXPENSE->value,
                'allow_negative' => true,
            ],
        ];

        // Crypto accounts
        $cryptoAssets = ['BTC', 'ETH', 'SOL', 'BNB', 'USDT'];
        foreach ($cryptoAssets as $crypto) {
            $systemAccounts[] = [
                'code' => "clearing:external:{$crypto}",
                'name' => "Rekening Kliring Eksternal {$crypto}",
                'asset_code' => $crypto,
                'kind' => AccountKind::CLEARING->value,
                'allow_negative' => true,
            ];
            $systemAccounts[] = [
                'code' => "exchange:{$crypto}",
                'name' => "Pool Likuiditas Exchange {$crypto}",
                'asset_code' => $crypto,
                'kind' => AccountKind::EXCHANGE->value,
                'allow_negative' => false,
            ];
            $systemAccounts[] = [
                'code' => "collateral:crypto:{$crypto}",
                'name' => "Akun Kolateral Kripto {$crypto}",
                'asset_code' => $crypto,
                'kind' => AccountKind::COLLATERAL->value,
                'allow_negative' => false,
            ];
        }

        foreach ($systemAccounts as $acc) {
            LedgerAccount::firstOrCreate(
                ['code' => $acc['code']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $acc['name'],
                    'asset_code' => $acc['asset_code'],
                    'kind' => $acc['kind'],
                    'allow_negative' => $acc['allow_negative'],
                    'cached_balance' => '0',
                    'is_frozen' => false,
                ]
            );
        }

        // Seed initial exchange liquidity via double-entry ledger
        $ledger = app(Ledger::class);

        $initialBalances = [
            'IDR' => '10000000000', // 10 Milyar IDR
            'BTC' => '100',
            'ETH' => '1000',
            'SOL' => '10000',
            'BNB' => '5000',
            'USDT' => '1000000',
        ];

        foreach ($initialBalances as $asset => $amount) {
            $amountBd = BigDecimal::of($amount);
            $idempotencyKey = "genesis:exchange:system:{$asset}";

            $ledger->post(new PostingDTO(
                type: TransactionType::GENESIS->value,
                description: "Penyediaan Likuiditas Awal Exchange {$asset}",
                idempotencyKey: $idempotencyKey,
                entries: [
                    PostingEntryDTO::forCode("clearing:external:{$asset}", $asset, $amountBd->negated()),
                    PostingEntryDTO::forCode("exchange:{$asset}", $asset, $amountBd),
                ],
                meta: ['genesis' => true, 'asset' => $asset, 'amount' => $amount],
                postedAt: now(),
            ));
        }
    }
}
