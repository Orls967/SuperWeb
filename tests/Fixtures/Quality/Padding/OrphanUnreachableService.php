<?php

declare(strict_types=1);

namespace Tests\Fixtures\Quality\Padding;

/**
 * Fixture kelas yatim (orphan) sengaja untuk pengujian aturan M-5 (T-10).
 * Tidak dipanggil atau diinjeksi oleh Controller, Command, Listener, maupun Provider mana pun.
 */
class OrphanUnreachableService
{
    public function lonelyOperation(): string
    {
        return 'unreachable_orphan_execution';
    }
}
