<?php

declare(strict_types=1);

namespace Modules\Payment\Contracts;

interface PaymentGatewayInterface
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function authorize(array $params): array;

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function capture(array $params = []): array;

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function refund(array $params = []): array;
}
