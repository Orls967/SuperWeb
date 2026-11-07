<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Enums;

enum ReconciliationRunStatus: string
{
    case OPEN = 'open';
    case COLLECTING = 'collecting';
    case BALANCING = 'balancing';
    case VARIANCE_DETECTED = 'variance_detected';
    case INVESTIGATING = 'investigating';
    case APPROVED = 'approved';
    case LOCKED = 'locked';
    case REOPENED = 'reopened';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Buka',
            self::COLLECTING => 'Pengumpulan Data',
            self::BALANCING => 'Penyeimbangan Neraca',
            self::VARIANCE_DETECTED => 'Deviasi Terdeteksi',
            self::INVESTIGATING => 'Dalam Investigasi',
            self::APPROVED => 'Disetujui',
            self::LOCKED => 'Terkunci',
            self::REOPENED => 'Dibuka Kembali',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::OPEN => 'bg-gray-100 text-gray-800',
            self::COLLECTING, self::BALANCING => 'bg-blue-100 text-blue-800',
            self::VARIANCE_DETECTED => 'bg-amber-100 text-amber-800',
            self::INVESTIGATING => 'bg-orange-100 text-orange-800',
            self::APPROVED => 'bg-emerald-100 text-emerald-800',
            self::LOCKED => 'bg-purple-100 text-purple-800',
            self::REOPENED => 'bg-red-100 text-red-800',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::LOCKED;
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::OPEN => in_array($target, [self::COLLECTING, self::OPEN], true),
            self::COLLECTING => in_array($target, [self::BALANCING, self::OPEN], true),
            self::BALANCING => in_array($target, [self::VARIANCE_DETECTED, self::APPROVED], true),
            self::VARIANCE_DETECTED => in_array($target, [self::INVESTIGATING, self::APPROVED], true),
            self::INVESTIGATING => in_array($target, [self::APPROVED, self::VARIANCE_DETECTED], true),
            self::APPROVED => in_array($target, [self::LOCKED, self::REOPENED], true),
            self::LOCKED => in_array($target, [self::REOPENED], true),
            self::REOPENED => in_array($target, [self::COLLECTING, self::BALANCING, self::OPEN], true),
        };
    }
}
