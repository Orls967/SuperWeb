<?php

declare(strict_types=1);

namespace Modules\Mall\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mall\Application\Actions\SettleVouchersAction;

class SettleVouchersCommand extends Command
{
    protected $signature = 'mall:settle-vouchers {--tenant= : ID Tenant spesifik}';

    protected $description = 'Disburse settlement voucher belanja mall yang telah digunakan ke wallet tenant';

    public function handle(SettleVouchersAction $action): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;

        $this->info('Menjalankan settlement voucher mall ke dompet tenant...');

        $result = $action->execute($tenantId);

        $this->info("✓ Settlement selesai: {$result['settled_count']} voucher berhasil di-settle ke {$result['tenants_count']} tenant dengan total nominal Rp ".number_format($result['total_amount']).'.');

        return self::SUCCESS;
    }
}
