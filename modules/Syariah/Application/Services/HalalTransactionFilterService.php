<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\Carbon;
use Modules\Syariah\Domain\Models\SybHalalCategory;
use Modules\Syariah\Domain\Models\SybHalalMerchant;
use RuntimeException;

final class HalalTransactionFilterService
{
    /**
     * Memverifikasi kepatuhan halal sebelum transaksi dompet syariah diproses.
     * Melempar exception jika melanggar ketentuan syariah.
     */
    public function validateMerchantForPayment(string $merchantCode, string $businessLineCode): SybHalalMerchant
    {
        $merchant = SybHalalMerchant::where('merchant_code', $merchantCode)->first();

        if (! $merchant) {
            throw new RuntimeException("Merchant [{$merchantCode}] is not registered in the Syariah ecosystem.");
        }

        if ($merchant->is_blacklisted) {
            throw new RuntimeException("Payment rejected: Merchant [{$merchantCode}] is blacklisted. Reason: {$merchant->blacklist_reason}");
        }

        // Cek kategori apakah terlarang keras (haram mutlak: judi, miras, tempat hiburan malam, bunga riba)
        $category = SybHalalCategory::where('category_code', $merchant->category_code)->first();
        if ($category && $category->is_strictly_prohibited) {
            throw new RuntimeException("Payment rejected: Business category [{$category->category_code}] is strictly prohibited in Syariah.");
        }

        // Cek sertifikasi halal jika kategori mewajibkannya (resto/makanan, farmasi, kosmetik, hotel syariah)
        $isMandatory = $category ? $category->is_halal_mandatory : true;
        if ($isMandatory) {
            if (empty($merchant->certificate_number)) {
                throw new RuntimeException("Payment rejected: Merchant [{$merchantCode}] does not possess a valid Halal certificate.");
            }

            if ($merchant->expires_at && Carbon::parse($merchant->expires_at)->isPast()) {
                throw new RuntimeException("Payment rejected: Halal certificate for merchant [{$merchantCode}] expired on {$merchant->expires_at->toDateString()}.");
            }
        }

        if ($merchant->status !== 'ACTIVE') {
            throw new RuntimeException("Payment rejected: Merchant [{$merchantCode}] is currently not active (status: {$merchant->status}).");
        }

        return $merchant;
    }

    /**
     * Cek status kepatuhan tanpa melempar exception.
     */
    public function isMerchantCompliant(string $merchantCode, string $businessLineCode): bool
    {
        try {
            $this->validateMerchantForPayment($merchantCode, $businessLineCode);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
