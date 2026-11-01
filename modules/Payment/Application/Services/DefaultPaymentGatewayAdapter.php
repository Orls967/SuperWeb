<?php

declare(strict_types=1);

namespace Modules\Payment\Application\Services;

use Modules\Payment\Contracts\PaymentGatewayInterface;

class DefaultPaymentGatewayAdapter implements PaymentGatewayInterface
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function authorize(array $params): array
    {
        return ['status' => 'authorized', 'params' => $params];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function capture(array $params = []): array
    {
        return ['status' => 'captured', 'params' => $params];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function refund(array $params = []): array
    {
        return ['status' => 'refunded', 'params' => $params];
    }
}
