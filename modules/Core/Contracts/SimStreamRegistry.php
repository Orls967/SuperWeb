<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface SimStreamRegistry
{
    /**
     * Mengambil seed deterministik terisolasi untuk nama aliran acak tertentu pada sesi aktif.
     *
     * @param  string  $streamName  Nama pengenal aliran (misalnya: 'tarif', 'demand', 'fraud')
     * @return int Seed integer positif untuk generator MINSTD
     */
    public function seedFor(string $streamName): int;
}
