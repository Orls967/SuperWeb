<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Enums;

enum BargeShipmentStatus: string
{
    case BERTHED = 'BERTHED';
    case LOADING = 'LOADING';
    case LOADED = 'LOADED';
    case DRAFT_SURVEYED = 'DRAFT_SURVEYED';
    case IN_TRANSIT = 'IN_TRANSIT';
    case ARRIVED_DESTINATION = 'ARRIVED_DESTINATION';
    case DISCHARGED = 'DISCHARGED';
    case SETTLED = 'SETTLED';

    public function label(): string
    {
        return match ($this) {
            self::BERTHED => 'Sandar di Jetty',
            self::LOADING => 'Pemuatan Bijih/Batubara',
            self::LOADED => 'Pemuatan Selesai',
            self::DRAFT_SURVEYED => 'Survei Draf Tervalidasi',
            self::IN_TRANSIT => 'Berlayar Menuju Tujuan',
            self::ARRIVED_DESTINATION => 'Tiba di Pelabuhan Bongkar',
            self::DISCHARGED => 'Pembongkaran Selesai',
            self::SETTLED => 'Tagihan Freight Lunas',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::BERTHED => 'bg-gray-100 text-gray-800',
            self::LOADING => 'bg-amber-100 text-amber-800',
            self::LOADED => 'bg-blue-100 text-blue-800',
            self::DRAFT_SURVEYED => 'bg-indigo-100 text-indigo-800',
            self::IN_TRANSIT => 'bg-cyan-100 text-cyan-800',
            self::ARRIVED_DESTINATION => 'bg-teal-100 text-teal-800',
            self::DISCHARGED => 'bg-purple-100 text-purple-800',
            self::SETTLED => 'bg-emerald-100 text-emerald-800',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::BERTHED => in_array($target, [self::LOADING], true),
            self::LOADING => in_array($target, [self::LOADED], true),
            self::LOADED => in_array($target, [self::DRAFT_SURVEYED], true),
            self::DRAFT_SURVEYED => in_array($target, [self::IN_TRANSIT], true),
            self::IN_TRANSIT => in_array($target, [self::ARRIVED_DESTINATION, self::DISCHARGED, self::SETTLED], true),
            self::ARRIVED_DESTINATION => in_array($target, [self::DISCHARGED], true),
            self::DISCHARGED => in_array($target, [self::SETTLED], true),
            self::SETTLED => false,
        };
    }
}
