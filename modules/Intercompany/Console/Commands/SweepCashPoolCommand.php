<?php

declare(strict_types=1);

namespace Modules\Intercompany\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Intercompany\Domain\Services\IntercompanyCashPoolSweeper;
use Psr\Clock\ClockInterface;

class SweepCashPoolCommand extends Command
{
    protected $signature = 'group:cash-pool-sweep {--pool=CPOOL-MAIN : Kode rekening header kas terpusat} {--date= : Tanggal sweep format YYYY-MM-DD}';

    protected $description = 'Menjalankan eksekusi liquidity sweep saldo nol rekening anak perusahaan ke header pool kas';

    public function handle(
        IntercompanyCashPoolSweeper $sweeper,
        ?ClockInterface $clock = null
    ): int {
        $now = $clock !== null ? CarbonImmutable::instance($clock->now()) : CarbonImmutable::now();
        $poolCode = $this->option('pool') ?: 'CPOOL-MAIN';
        $date = $this->option('date') ?: $now->format('Y-m-d');

        $this->info("Memulai eksekusi sweep likuiditas intercompany cash pool [{$poolCode}] tanggal [{$date}]...");

        $config = [
            'pool_code' => $poolCode,
            'sweep_date' => $date,
            'header_entity_code' => 'HOLDING_CORP',
            'currency' => 'IDR',
            'target_target_balance_minor' => 100_000_000_00, // Saldo operasi minimum Rp 100jt
            'pool_deposit_rate_bps' => 550,
            'pool_borrowing_rate_bps' => 750,
            'wht_rate_bps' => 1500,
        ];

        // Simulasi rekening operasional entitas peserta cash pool
        $participantAccounts = [
            [
                'entity_code' => 'ENT_MANUF_01',
                'account_number' => 'BANK-IDR-MFG-001',
                'current_balance_minor' => 450_000_000_00, // Surplus likuiditas Rp 350jt
                'minimum_operating_buffer_minor' => 100_000_000_00,
            ],
            [
                'entity_code' => 'ENT_RETAIL_01',
                'account_number' => 'BANK-IDR-RET-001',
                'current_balance_minor' => 40_000_000_00, // Defisit likuiditas Rp 60jt
                'minimum_operating_buffer_minor' => 100_000_000_00,
            ],
            [
                'entity_code' => 'ENT_LOGISTICS_01',
                'account_number' => 'BANK-IDR-LGX-001',
                'current_balance_minor' => 100_000_000_00, // Tepat di batas saldo operasi
                'minimum_operating_buffer_minor' => 100_000_000_00,
            ],
        ];

        $res = $sweeper->executeSweep($config, $participantAccounts);

        $this->info("Sweep kas selesai: Sesi [{$res['sweep_session_code']}]");
        $this->line('Total Likuiditas Ditarik (Surplus In): Rp '.number_format((float) $res['total_swept_in_minor'], 0, ',', '.'));
        $this->line('Total Likuiditas Disalurkan (Defisit Out): Rp '.number_format((float) $res['total_funded_out_minor'], 0, ',', '.'));
        $this->line('Perubahan Bersih Kas Terpusat: Rp '.number_format((float) $res['net_pool_liquidity_change_minor'], 0, ',', '.'));

        $headers = ['Kode Transfer', 'Arah', 'Entitas', 'Nominal', 'Bunga Akrual', 'PPh 23'];
        $rows = [];

        foreach ($res['transfers'] as $trf) {
            $rows[] = [
                $trf['transfer_code'],
                $trf['transfer_direction'],
                $trf['participant_entity_code'],
                'Rp '.number_format((float) $trf['amount_minor'], 0, ',', '.'),
                'Rp '.number_format((float) $trf['accrued_interest_minor'], 0, ',', '.'),
                'Rp '.number_format((float) $trf['withholding_tax_minor'], 0, ',', '.'),
            ];
        }

        $this->table($headers, $rows);

        return self::SUCCESS;
    }
}
