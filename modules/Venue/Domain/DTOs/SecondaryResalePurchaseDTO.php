<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\DTOs;

final class SecondaryResalePurchaseDTO
{
    public function __construct(
        public readonly int $resaleId,
        public readonly int $buyerUserId,
        public readonly int $offeredPriceIdr,
        public readonly string $paymentMethod,
        public readonly ?string $buyerIp = null,
        public readonly ?string $buyerDeviceFingerprint = null,
        public readonly ?string $buyerNik = null,
    ) {
        if ($this->resaleId <= 0) {
            throw new \InvalidArgumentException('resaleId harus positif');
        }
        if ($this->buyerUserId <= 0) {
            throw new \InvalidArgumentException('buyerUserId harus positif');
        }
        if ($this->offeredPriceIdr <= 0) {
            throw new \InvalidArgumentException('offeredPriceIdr harus positif');
        }
        if (trim($this->paymentMethod) === '') {
            throw new \InvalidArgumentException('paymentMethod tidak boleh kosong');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'resale_id' => $this->resaleId,
            'buyer_user_id' => $this->buyerUserId,
            'offered_price_idr' => $this->offeredPriceIdr,
            'payment_method' => $this->paymentMethod,
            'buyer_ip' => $this->buyerIp,
            'buyer_device_fingerprint' => $this->buyerDeviceFingerprint,
            'buyer_nik' => $this->buyerNik,
        ];
    }
}
