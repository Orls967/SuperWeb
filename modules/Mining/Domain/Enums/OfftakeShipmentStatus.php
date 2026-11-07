<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Enums;

enum OfftakeShipmentStatus: string
{
    case CONTRACTED = 'contracted';
    case NOMINATED = 'nominated';
    case LC_OPENED = 'lc_opened';
    case LOADING = 'loading';
    case PROVISIONAL_INVOICED = 'provisional_invoiced';
    case FINAL_ASSAYED = 'final_assayed';
    case FINAL_INVOICED = 'final_invoiced';
    case SETTLED = 'settled';
    case DISPUTED = 'disputed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::CONTRACTED => 'Terkontrak',
            self::NOMINATED => 'Kapal Dinominasikan',
            self::LC_OPENED => 'Letter of Credit Terbit',
            self::LOADING => 'Pemuatan Tongkang/Kapal',
            self::PROVISIONAL_INVOICED => 'Invoice Provisional Terbit',
            self::FINAL_ASSAYED => 'Hasil Assay Final Umpire',
            self::FINAL_INVOICED => 'Invoice Final Diselaraskan',
            self::SETTLED => 'Pembayaran Lunas',
            self::DISPUTED => 'Sengketa Kualitas / Dokumen',
            self::CANCELLED => 'Dibatalkan / Dialihkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::CONTRACTED => 'bg-gray-100 text-gray-800',
            self::NOMINATED => 'bg-blue-100 text-blue-800',
            self::LC_OPENED => 'bg-cyan-100 text-cyan-800',
            self::LOADING => 'bg-amber-100 text-amber-800',
            self::PROVISIONAL_INVOICED => 'bg-indigo-100 text-indigo-800',
            self::FINAL_ASSAYED => 'bg-teal-100 text-teal-800',
            self::FINAL_INVOICED => 'bg-emerald-100 text-emerald-800',
            self::SETTLED => 'bg-purple-100 text-purple-800',
            self::DISPUTED => 'bg-orange-100 text-orange-800',
            self::CANCELLED => 'bg-red-100 text-red-800',
        };
    }

    public function isSettledOrCancelled(): bool
    {
        return $this === self::SETTLED || $this === self::CANCELLED;
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::CONTRACTED => in_array($target, [self::NOMINATED, self::CANCELLED, self::CONTRACTED], true),
            self::NOMINATED => in_array($target, [self::LC_OPENED, self::CANCELLED], true),
            self::LC_OPENED => in_array($target, [self::LOADING, self::DISPUTED, self::CANCELLED], true),
            self::LOADING => in_array($target, [self::PROVISIONAL_INVOICED, self::DISPUTED, self::CANCELLED], true),
            self::PROVISIONAL_INVOICED => in_array($target, [self::FINAL_ASSAYED, self::DISPUTED], true),
            self::FINAL_ASSAYED => in_array($target, [self::FINAL_INVOICED, self::DISPUTED], true),
            self::FINAL_INVOICED => in_array($target, [self::SETTLED, self::DISPUTED], true),
            self::DISPUTED => in_array($target, [self::FINAL_INVOICED, self::SETTLED, self::CANCELLED], true),
            self::SETTLED, self::CANCELLED => false,
        };
    }
}
