<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Enums;

enum GeotechIncidentStatus: string
{
    case MONITORING_NORMAL = 'MONITORING_NORMAL';
    case THRESHOLD_EXCEEDED = 'THRESHOLD_EXCEEDED';
    case PRE_WARNING = 'PRE_WARNING';
    case ALARM_TRIGGERED = 'ALARM_TRIGGERED';
    case AREA_EVACUATED = 'AREA_EVACUATED';
    case INSPECTED_BY_GEOTECH = 'INSPECTED_BY_GEOTECH';
    case REMEDIATED = 'REMEDIATED';
    case ALL_CLEAR = 'ALL_CLEAR';

    public function label(): string
    {
        return match ($this) {
            self::MONITORING_NORMAL => 'Pemantauan Normal',
            self::THRESHOLD_EXCEEDED => 'Ambang Awal Terlewati',
            self::PRE_WARNING => 'Peringatan Dini Geoteknik',
            self::ALARM_TRIGGERED => 'Alarm Darurat Bahaya Longsor',
            self::AREA_EVACUATED => 'Area Sektor Dievakuasi',
            self::INSPECTED_BY_GEOTECH => 'Investigasi Ahli Geoteknik',
            self::REMEDIATED => 'Remediasi & Pelandaian Lereng',
            self::ALL_CLEAR => 'Lereng Stabil & Area Dibuka',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::MONITORING_NORMAL => 'bg-emerald-100 text-emerald-800',
            self::THRESHOLD_EXCEEDED => 'bg-blue-100 text-blue-800',
            self::PRE_WARNING => 'bg-amber-100 text-amber-800',
            self::ALARM_TRIGGERED => 'bg-red-100 text-red-800',
            self::AREA_EVACUATED => 'bg-rose-100 text-rose-800',
            self::INSPECTED_BY_GEOTECH => 'bg-purple-100 text-purple-800',
            self::REMEDIATED => 'bg-indigo-100 text-indigo-800',
            self::ALL_CLEAR => 'bg-teal-100 text-teal-800',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::MONITORING_NORMAL => in_array($target, [self::THRESHOLD_EXCEEDED, self::PRE_WARNING, self::ALARM_TRIGGERED], true),
            self::THRESHOLD_EXCEEDED => in_array($target, [self::PRE_WARNING, self::ALARM_TRIGGERED, self::MONITORING_NORMAL], true),
            self::PRE_WARNING => in_array($target, [self::ALARM_TRIGGERED, self::INSPECTED_BY_GEOTECH, self::MONITORING_NORMAL], true),
            self::ALARM_TRIGGERED => in_array($target, [self::AREA_EVACUATED], true),
            self::AREA_EVACUATED => in_array($target, [self::INSPECTED_BY_GEOTECH, self::REMEDIATED, self::ALL_CLEAR], true),
            self::INSPECTED_BY_GEOTECH => in_array($target, [self::REMEDIATED, self::ALL_CLEAR], true),
            self::REMEDIATED => in_array($target, [self::ALL_CLEAR], true),
            self::ALL_CLEAR => in_array($target, [self::MONITORING_NORMAL, self::THRESHOLD_EXCEEDED], true),
        };
    }
}
