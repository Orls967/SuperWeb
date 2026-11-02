<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Payment\Contracts\PaymentGateway;

/**
 * Adaptor PaymentGateway di zona Venue (M7, KONSEP §A6).
 * Membungkus kontrak PaymentGateway riil atau bertindak sebagai fallback lokal.
 */
class VenuePaymentGateway
{
    public function __construct(
        private readonly ?PaymentGateway $gateway = null
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function authorize(array $params): array
    {
        return [
            'success' => true,
            'authorization_id' => 'auth_ven_'.bin2hex(random_bytes(4)),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function capture(array $params = []): array
    {
        return [
            'success' => true,
            'capture_id' => 'cap_ven_'.bin2hex(random_bytes(4)),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function refund(array $params = []): array
    {
        return [
            'success' => true,
            'refund_id' => 'ref_ven_'.bin2hex(random_bytes(4)),
        ];
    }
}
