<?php

declare(strict_types=1);

namespace Modules\Core\Application\Projections;

use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Antarmuka Reducer Proyeksi Deterministik (W8-D39)
 */
interface ProjectionReducerInterface
{
    /**
     * Topik atau nama event yang didukung oleh reducer ini.
     *
     * @return array<int, string>
     */
    public function supportedTopics(): array;

    /**
     * Reducer fungsi murni: menerima status akumulasi saat ini dan event,
     * mengembalikan status akumulasi baru secara deterministik tanpa efek samping eksternal.
     *
     * @param  array<string, mixed>  $currentState
     * @return array<string, mixed>
     */
    public function reduce(array $currentState, SimEventSpine $event): array;
}

