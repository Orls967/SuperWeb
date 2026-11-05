<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Support;

use Modules\Core\Contracts\DocumentNumberingInterface;

/**
 * Penomoran dokumen gapless modul Insurance (KONSEP §A3): `{JENIS}-{TAHUN}-{URUT}` per entitas, jenis, dan tahun.
 */
final class InsuranceNumbering
{
    public const string ENTITY = 'INS';

    public static function policy(DocumentNumberingInterface $numbering, string $lineCategory): string
    {
        return self::next($numbering, 'POL-'.$lineCategory, 6);
    }

    public static function claim(DocumentNumberingInterface $numbering, string $lineCategory): string
    {
        return self::next($numbering, 'CLM-'.$lineCategory, 6);
    }

    public static function treaty(DocumentNumberingInterface $numbering): string
    {
        return self::next($numbering, 'TRT', 4);
    }

    public static function bordereau(DocumentNumberingInterface $numbering): string
    {
        return self::next($numbering, 'BDX', 5);
    }

    public static function accountCurrent(DocumentNumberingInterface $numbering): string
    {
        return self::next($numbering, 'RAC', 5);
    }

    public static function next(DocumentNumberingInterface $numbering, string $documentType, int $padding): string
    {
        return $numbering->nextNumber(self::ENTITY, $documentType, false, '{TYPE}-{YYYY}-', $padding);
    }
}
