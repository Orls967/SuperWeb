<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Enums;

enum FurnaceRunStatus: string
{
    case DRAFT = 'DRAFT';
    case CHARGING = 'CHARGING';
    case PREHEATING = 'PREHEATING';
    case SMELTING = 'SMELTING';
    case TAPPING = 'TAPPING';
    case COMPLETED = 'COMPLETED';
    case ABORTED = 'ABORTED';
    case COOLING = 'COOLING';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Rencana Run Tanur',
            self::CHARGING => 'Pengisian Feed Bijih & Reduktor',
            self::PREHEATING => 'Pemanasan Awal Tanur',
            self::SMELTING => 'Peleburan Reduksi Berlangsung',
            self::TAPPING => 'Penuangan Logam & Terak (Tapping)',
            self::COMPLETED => 'Siklus Run Selesai Sukses',
            self::ABORTED => 'Peleburan Dibatalkan / Trip Darurat',
            self::COOLING => 'Pendinginan Tanur Terkendali',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-gray-100 text-gray-800',
            self::CHARGING => 'bg-amber-100 text-amber-800',
            self::PREHEATING => 'bg-orange-100 text-orange-800',
            self::SMELTING => 'bg-red-100 text-red-800',
            self::TAPPING => 'bg-purple-100 text-purple-800',
            self::COMPLETED => 'bg-emerald-100 text-emerald-800',
            self::ABORTED => 'bg-rose-100 text-rose-800',
            self::COOLING => 'bg-blue-100 text-blue-800',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::DRAFT => in_array($target, [self::CHARGING, self::ABORTED], true),
            self::CHARGING => in_array($target, [self::PREHEATING, self::ABORTED], true),
            self::PREHEATING => in_array($target, [self::SMELTING, self::ABORTED], true),
            self::SMELTING => in_array($target, [self::TAPPING, self::ABORTED], true),
            self::TAPPING => in_array($target, [self::COMPLETED, self::COOLING, self::ABORTED], true),
            self::COMPLETED => in_array($target, [self::COOLING], true),
            self::ABORTED => in_array($target, [self::COOLING], true),
            self::COOLING => false,
        };
    }
}
