<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Enums;

enum ShipmentStatus: string
{
    case Draft = 'draft';
    case Booked = 'booked';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case AtHub = 'at_hub';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case OnHold = 'on_hold';
    case CustomsHold = 'customs_hold';
    case Exception = 'exception';
    case ReturnToSender = 'return_to_sender';
    case Returned = 'returned';
    case Lost = 'lost';
    case Cancelled = 'cancelled';

    /**
     * Check whether this status can transition to the target status.
     */
    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return true;
        }

        return match ($this) {
            self::Draft => in_array($target, [self::Booked, self::Cancelled], true),
            self::Booked => in_array($target, [self::PickedUp, self::Cancelled, self::OnHold], true),
            self::PickedUp => in_array($target, [self::InTransit, self::AtHub, self::Exception, self::OnHold, self::CustomsHold, self::ReturnToSender], true),
            self::InTransit => in_array($target, [self::AtHub, self::OutForDelivery, self::Exception, self::OnHold, self::CustomsHold, self::Lost, self::ReturnToSender], true),
            self::AtHub => in_array($target, [self::InTransit, self::OutForDelivery, self::Exception, self::OnHold, self::CustomsHold, self::ReturnToSender], true),
            self::OutForDelivery => in_array($target, [self::Delivered, self::Exception, self::ReturnToSender, self::Lost], true),
            self::OnHold => in_array($target, [self::Booked, self::PickedUp, self::InTransit, self::AtHub, self::Cancelled, self::ReturnToSender], true),
            self::CustomsHold => in_array($target, [self::InTransit, self::AtHub, self::Exception, self::ReturnToSender], true),
            self::Exception => in_array($target, [self::InTransit, self::AtHub, self::OutForDelivery, self::ReturnToSender, self::Lost], true),
            self::ReturnToSender => in_array($target, [self::Returned, self::Lost], true),
            self::Delivered, self::Returned, self::Lost, self::Cancelled => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Booked => 'Telah Dipesan',
            self::PickedUp => 'Sudah Di-Pickup',
            self::InTransit => 'Dalam Perjalanan',
            self::AtHub => 'Tiba di Hub',
            self::OutForDelivery => 'Sedang Diantar',
            self::Delivered => 'Terkirim',
            self::OnHold => 'Ditahan (On Hold)',
            self::CustomsHold => 'Pemeriksaan Bea Cukai',
            self::Exception => 'Kendala / Exception',
            self::ReturnToSender => 'Dikembalikan ke Pengirim',
            self::Returned => 'Selesai Dikembalikan',
            self::Lost => 'Hilang',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-700/60 text-slate-300 border-slate-600',
            self::Booked => 'bg-blue-900/50 text-blue-300 border-blue-700',
            self::PickedUp => 'bg-sky-900/50 text-sky-300 border-sky-700',
            self::InTransit => 'bg-cyan-900/50 text-cyan-300 border-cyan-700',
            self::AtHub => 'bg-indigo-900/50 text-indigo-300 border-indigo-700',
            self::OutForDelivery => 'bg-amber-900/50 text-amber-300 border-amber-700',
            self::Delivered => 'bg-emerald-900/50 text-emerald-300 border-emerald-700',
            self::OnHold, self::CustomsHold => 'bg-purple-900/50 text-purple-300 border-purple-700',
            self::Exception => 'bg-rose-900/50 text-rose-300 border-rose-700',
            self::ReturnToSender, self::Returned => 'bg-orange-900/50 text-orange-300 border-orange-700',
            self::Lost, self::Cancelled => 'bg-gray-800 text-gray-400 border-gray-700',
        };
    }
}
