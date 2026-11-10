<?php

declare(strict_types=1);

namespace Modules\Treasury\Ledger;

/**
 * W8-D61: Factory pembentuk kode akun multi-entitas pada ledger modul Banking.
 *
 * Pola format:
 *   {prefiks}:{nama_akun}:{entitas}[:{lawan}]:{mata_uang}
 */
final class EntityAccountFactory
{
    public static function forTreasury(string $accountName, string $entityCode = 'HOLDING', string $currency = 'IDR'): string
    {
        $acc = strtolower(trim($accountName));
        $ent = strtoupper(trim($entityCode));
        $ccy = strtoupper(trim($currency));

        return "trs:{$acc}:{$ent}:{$ccy}";
    }

    public static function forIntercompany(string $accountType, string $entityCode, string $counterpartyCode, string $currency = 'IDR'): string
    {
        $type = strtolower(trim($accountType));
        $ent = strtoupper(trim($entityCode));
        $cp = strtoupper(trim($counterpartyCode));
        $ccy = strtoupper(trim($currency));

        return "ic:{$type}:{$ent}:{$cp}:{$ccy}";
    }

    public static function forPoolSweepClearing(string $childEntityCode, string $currency = 'IDR'): string
    {
        $ent = strtoupper(trim($childEntityCode));
        $ccy = strtoupper(trim($currency));

        return "trs:pool_sweep_clearing:{$ent}:{$ccy}";
    }
}
