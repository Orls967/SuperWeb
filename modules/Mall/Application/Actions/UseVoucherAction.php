<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Mall\Domain\Enums\VoucherStatus;
use Modules\Mall\Domain\Exceptions\InvalidVoucherException;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\Voucher;

class UseVoucherAction
{
    /**
     * Gunakan voucher belanja mall di outlet tenant.
     *
     * @throws InvalidVoucherException
     */
    public function execute(
        string $voucherCode,
        Tenant $tenant,
        int $transactionAmount,
        ?string $transactionRef = null
    ): Voucher {
        $voucherId = Voucher::query()
            ->where('voucher_code', trim(strtoupper($voucherCode)))
            ->value('id');

        if (! $voucherId) {
            throw new InvalidVoucherException("Voucher dengan kode {$voucherCode} tidak ditemukan.");
        }

        return DB::transaction(function () use ($voucherId, $voucherCode, $tenant, $transactionAmount, $transactionRef) {
            $voucher = Voucher::query()->lockForUpdate()->find($voucherId);

            if (! $voucher) {
                throw new InvalidVoucherException("Voucher dengan kode {$voucherCode} tidak ditemukan.");
            }

            if (! $voucher->isValid()) {
                throw new InvalidVoucherException('Voucher tidak valid atau sudah kedaluwarsa.');
            }

            if ($voucher->tenant_id !== null && $voucher->tenant_id !== $tenant->id) {
                throw new InvalidVoucherException('Voucher ini hanya berlaku di tenant yang telah ditentukan.');
            }

            if ($transactionAmount < $voucher->min_spend) {
                throw new InvalidVoucherException('Total belanja (Rp '.number_format($transactionAmount).') belum memenuhi batas minimal belanja voucher (Rp '.number_format($voucher->min_spend).').');
            }

            $voucher->update([
                'status' => VoucherStatus::USED,
                'used_at' => now(),
                'used_at_tenant_id' => $tenant->id,
                'used_transaction_ref' => $transactionRef,
            ]);

            return $voucher;
        });
    }
}
