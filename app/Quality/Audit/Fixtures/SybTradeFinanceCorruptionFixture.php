<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class SybTradeFinanceCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'syb:audit';
    }

    public function corrupt(): string
    {
        // 1. Jika tabel syb_scf_invoices ada, buat invoice aktif tanpa verifikasi qabdh
        if (Schema::hasTable('syb_scf_invoices') && Schema::hasTable('syb_scf_facilities')) {
            $facilityId = DB::table('syb_scf_facilities')->insertGetId([
                'facility_number' => 'SCF-CORRUPT-'.Str::random(6),
                'buyer_party_ref' => 'buyer-corrupt-party',
                'supplier_party_ref' => 'supplier-corrupt-party',
                'limit_amount_cents' => 1_000_000_000,
                'utilized_amount_cents' => 500_000_000,
                'margin_rate_bps' => 600,
                'max_tenor_days' => 90,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('syb_scf_invoices')->insert([
                'facility_id' => $facilityId,
                'invoice_number' => 'INV-CORRUPT-'.Str::random(6),
                'supplier_invoice_ref' => 'SUPP-INV-CORRUPT',
                'principal_cost_cents' => 500_000_000,
                'margin_profit_cents' => 25_000_000,
                'total_sale_price_cents' => 525_000_000,
                'custody_document_ref' => null,
                'qabdh_verified_at' => null, // Pelanggaran: status active tanpa qabdh
                'due_date' => now()->addDays(90)->toDateString(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return 'syb.unverified_qabdh_scf_invoice';
        }

        // 2. Fallback: Menyuntikkan akun bunga riba perdagangan luar negeri
        DB::table('bank_ledger_accounts')->insert([
            'uuid' => (string) Str::uuid(),
            'code' => 'syb:forbidden_trade_interest_penalty:IDR',
            'asset_code' => 'IDR',
            'kind' => 'revenue',
            'name' => 'Forbidden Trade Finance Conventional Interest Penalty',
            'allow_negative' => false,
            'cached_balance' => '5000000',
            'is_frozen' => false,
            'created_at' => '2026-11-06 00:00:00',
            'updated_at' => '2026-11-06 00:00:00',
        ]);

        return 'syb.forbidden_interest_account';
    }
}
