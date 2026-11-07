<?php

declare(strict_types=1);

namespace Modules\Venue\database\seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Venue\Domain\Algorithms\Marketplace\SecondaryMarketTaxWaterfallEngine;
use Modules\Venue\Domain\Algorithms\Marketplace\SecondaryMarketTaxWaterfallInput;
use Modules\Venue\Domain\Models\DynamicBundleComponent;
use Modules\Venue\Domain\Models\DynamicBundlePackage;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\ScalperBlacklistLog;
use Modules\Venue\Domain\Models\SecondaryLedgerEntry;
use Modules\Venue\Domain\Models\TicketResale;
use Modules\Venue\Domain\Models\TicketWaitlist;
use Modules\Venue\Domain\Models\WaitlistReservation;
use Modules\Venue\Domain\StateMachines\DynamicBundlePackageStateMachine;
use Modules\Venue\Domain\StateMachines\TicketResaleStateMachine;
use Modules\Venue\Domain\StateMachines\WaitlistStateMachine;

final class VenueSecondaryDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Dapatkan atau buat pengguna demo
        $organizer = User::firstOrCreate(
            ['email' => 'promoter.secondary@superweb.test'],
            ['name' => 'Promotor Soundrenaline Jakarta', 'password' => bcrypt('secret123')]
        );

        $sellers = [];
        for ($i = 1; $i <= 5; $i++) {
            $sellers[$i] = User::firstOrCreate(
                ['email' => "seller.demo{$i}@superweb.test"],
                ['name' => "Mitra Penjual Sekunder #{$i}", 'password' => bcrypt('secret123')]
            );
        }

        $buyers = [];
        for ($i = 1; $i <= 5; $i++) {
            $buyers[$i] = User::firstOrCreate(
                ['email' => "buyer.demo{$i}@superweb.test"],
                ['name' => "Kolektor Musik Buyer #{$i}", 'password' => bcrypt('secret123')]
            );
        }

        $scalperUser = User::firstOrCreate(
            ['email' => 'bot.scalper.crawler@superweb.test'],
            ['name' => 'Akun Bot Scalper Crawler', 'password' => bcrypt('secret123')]
        );

        // 2. Buat Event Hiburan untuk Pasar Sekunder & Dynamic Bundling
        $event = EntertainmentEvent::firstOrCreate(
            ['event_code' => 'EVT-SEC-2026-SOUNDWAVE'],
            [
                'venue_id' => 1,
                'title' => 'Nusantara Soundwave Symphony 2026',
                'doors_open_at' => now()->addDays(30)->setTime(17, 0),
                'doors_closed_at' => now()->addDays(30)->setTime(23, 30),
                'ticket_floor_price_idr' => 500000,
                'ticket_ceiling_price_idr' => 3500000,
                'current_ticket_price_idr' => 1500000,
                'status' => 'PUBLISHED',
            ]
        );

        // 3. Kasus T1 1..6: Tiket Asli & Secondary Resale Listings
        $waterfallEngine = new SecondaryMarketTaxWaterfallEngine;

        // Kasus T1.1: Listing Aktif (Harga Normal 110%)
        $t1 = EntertainmentTicket::create([
            'ticket_uuid' => Str::uuid()->toString(),
            'ticket_number' => 'TCK-SEC-001-'.Str::upper(Str::random(6)),
            'event_id' => $event->id,
            'buyer_user_id' => $sellers[1]->id,
            'price_paid_idr' => 1500000,
            'tax_paid_idr' => 150000,
            'ticket_hash' => hash('sha256', 'TCK-SEC-001-SEED'),
            'prev_hash' => hash('sha256', 'GENESIS'),
            'idempotency_key' => 'IDEMP-SEC-DEMO-001',
            'status' => 'ISSUED',
        ]);
        TicketResale::create([
            'resale_code' => 'RSL-'.Str::upper(Str::random(8)),
            'ticket_id' => $t1->id,
            'seller_user_id' => $sellers[1]->id,
            'original_face_value_idr' => 1500000,
            'resale_price_idr' => 1650000,
            'platform_fee_idr' => 132000,
            'tax_pbjt_idr' => 165000,
            'promoter_royalty_idr' => 33000,
            'seller_payout_idr' => 1320000,
            'transfer_count' => 0,
            'status' => TicketResaleStateMachine::STATUS_LISTED,
        ]);

        // Kasus T1.2: Listing Aktif (Harga Cap Maksimum Persis 120%)
        $t2 = EntertainmentTicket::create([
            'ticket_uuid' => Str::uuid()->toString(),
            'ticket_number' => 'TCK-SEC-002-'.Str::upper(Str::random(6)),
            'event_id' => $event->id,
            'buyer_user_id' => $sellers[2]->id,
            'price_paid_idr' => 1500000,
            'tax_paid_idr' => 150000,
            'ticket_hash' => hash('sha256', 'TCK-SEC-002-SEED'),
            'prev_hash' => hash('sha256', 'GENESIS'),
            'idempotency_key' => 'IDEMP-SEC-DEMO-002',
            'status' => 'ISSUED',
        ]);
        TicketResale::create([
            'resale_code' => 'RSL-'.Str::upper(Str::random(8)),
            'ticket_id' => $t2->id,
            'seller_user_id' => $sellers[2]->id,
            'original_face_value_idr' => 1500000,
            'resale_price_idr' => 1800000,
            'platform_fee_idr' => 144000,
            'tax_pbjt_idr' => 180000,
            'promoter_royalty_idr' => 36000,
            'seller_payout_idr' => 1440000,
            'transfer_count' => 0,
            'status' => TicketResaleStateMachine::STATUS_LISTED,
        ]);

        // Kasus T1.3: Listing Aktif (Harga Diskon di Bawah Face Value / 90%)
        $t3 = EntertainmentTicket::create([
            'ticket_uuid' => Str::uuid()->toString(),
            'ticket_number' => 'TCK-SEC-003-'.Str::upper(Str::random(6)),
            'event_id' => $event->id,
            'buyer_user_id' => $sellers[3]->id,
            'price_paid_idr' => 800000,
            'tax_paid_idr' => 80000,
            'ticket_hash' => hash('sha256', 'TCK-SEC-003-SEED'),
            'prev_hash' => hash('sha256', 'GENESIS'),
            'idempotency_key' => 'IDEMP-SEC-DEMO-003',
            'status' => 'ISSUED',
        ]);
        TicketResale::create([
            'resale_code' => 'RSL-'.Str::upper(Str::random(8)),
            'ticket_id' => $t3->id,
            'seller_user_id' => $sellers[3]->id,
            'original_face_value_idr' => 800000,
            'resale_price_idr' => 720000,
            'platform_fee_idr' => 57600,
            'tax_pbjt_idr' => 72000,
            'promoter_royalty_idr' => 14400,
            'seller_payout_idr' => 576000,
            'transfer_count' => 0,
            'status' => TicketResaleStateMachine::STATUS_LISTED,
        ]);

        // Kasus T1.4: Listing Status ESCROW_HOLD (Sedang Diproses Pembeli)
        $t4 = EntertainmentTicket::create([
            'ticket_uuid' => Str::uuid()->toString(),
            'ticket_number' => 'TCK-SEC-004-'.Str::upper(Str::random(6)),
            'event_id' => $event->id,
            'buyer_user_id' => $sellers[4]->id,
            'price_paid_idr' => 800000,
            'tax_paid_idr' => 80000,
            'ticket_hash' => hash('sha256', 'TCK-SEC-004-SEED'),
            'prev_hash' => hash('sha256', 'GENESIS'),
            'idempotency_key' => 'IDEMP-SEC-DEMO-004',
            'status' => 'ISSUED',
        ]);
        TicketResale::create([
            'resale_code' => 'RSL-'.Str::upper(Str::random(8)),
            'ticket_id' => $t4->id,
            'seller_user_id' => $sellers[4]->id,
            'buyer_user_id' => $buyers[1]->id,
            'original_face_value_idr' => 800000,
            'resale_price_idr' => 850000,
            'platform_fee_idr' => 68000,
            'tax_pbjt_idr' => 85000,
            'promoter_royalty_idr' => 17000,
            'seller_payout_idr' => 680000,
            'transfer_count' => 0,
            'status' => TicketResaleStateMachine::STATUS_ESCROW_HOLD,
            'escrow_held_at' => now()->subMinutes(10),
        ]);

        // Kasus T1.5: Transaksi Sukses SETTLED dengan Rotated Hash & Waterfall Ledger
        $t5 = EntertainmentTicket::create([
            'ticket_uuid' => Str::uuid()->toString(),
            'ticket_number' => 'TCK-SEC-005-'.Str::upper(Str::random(6)),
            'event_id' => $event->id,
            'buyer_user_id' => $buyers[2]->id,
            'price_paid_idr' => 3000000,
            'tax_paid_idr' => 300000,
            'ticket_hash' => hash('sha256', 'TCK-SEC-005-ORIGINAL-SEED'),
            'prev_hash' => hash('sha256', 'GENESIS'),
            'idempotency_key' => 'IDEMP-SEC-DEMO-005',
            'status' => 'ISSUED',
        ]);
        $waterfallCalc5 = $waterfallEngine->calculate(new SecondaryMarketTaxWaterfallInput(3300000, 1000, 800, 200));
        $resaleSettled5 = TicketResale::create([
            'resale_code' => 'RSL-'.Str::upper(Str::random(8)),
            'ticket_id' => $t5->id,
            'seller_user_id' => $sellers[5]->id,
            'buyer_user_id' => $buyers[2]->id,
            'original_face_value_idr' => 3000000,
            'resale_price_idr' => 3300000,
            'tax_pbjt_idr' => $waterfallCalc5->taxPbjtIdr,
            'platform_fee_idr' => $waterfallCalc5->platformFeeIdr,
            'promoter_royalty_idr' => $waterfallCalc5->promoterRoyaltyIdr,
            'seller_payout_idr' => $waterfallCalc5->sellerPayoutIdr,
            'transfer_count' => 1,
            'new_ticket_hash' => hash('sha256', 'TCK-SEC-005-ROTATED-NEW-BARCODE-TOKEN'),
            'status' => TicketResaleStateMachine::STATUS_SETTLED,
            'escrow_held_at' => now()->subDays(2),
            'settled_at' => now()->subDay(),
        ]);
        SecondaryLedgerEntry::create([
            'entry_code' => 'LEDGER-'.Str::upper(Str::random(10)),
            'resale_id' => $resaleSettled5->id,
            'debit_account' => 'ESCROW_CLEARING',
            'credit_account' => 'SETTLED_DISTRIBUTION',
            'amount_idr' => 3300000,
            'description' => 'Konservasi akuntansi tertutup pasar sekunder Royal Box',
        ]);

        // Kasus T1.6: Transaksi Sukses SETTLED Lainnya (Festival)
        $t6 = EntertainmentTicket::create([
            'ticket_uuid' => Str::uuid()->toString(),
            'ticket_number' => 'TCK-SEC-006-'.Str::upper(Str::random(6)),
            'event_id' => $event->id,
            'buyer_user_id' => $buyers[3]->id,
            'price_paid_idr' => 500000,
            'tax_paid_idr' => 50000,
            'ticket_hash' => hash('sha256', 'TCK-SEC-006-ORIGINAL-SEED'),
            'prev_hash' => hash('sha256', 'GENESIS'),
            'idempotency_key' => 'IDEMP-SEC-DEMO-006',
            'status' => 'ISSUED',
        ]);
        $waterfallCalc6 = $waterfallEngine->calculate(new SecondaryMarketTaxWaterfallInput(550000, 1000, 800, 200));
        $resaleSettled6 = TicketResale::create([
            'resale_code' => 'RSL-'.Str::upper(Str::random(8)),
            'ticket_id' => $t6->id,
            'seller_user_id' => $sellers[1]->id,
            'buyer_user_id' => $buyers[3]->id,
            'original_face_value_idr' => 500000,
            'resale_price_idr' => 550000,
            'tax_pbjt_idr' => $waterfallCalc6->taxPbjtIdr,
            'platform_fee_idr' => $waterfallCalc6->platformFeeIdr,
            'promoter_royalty_idr' => $waterfallCalc6->promoterRoyaltyIdr,
            'seller_payout_idr' => $waterfallCalc6->sellerPayoutIdr,
            'transfer_count' => 1,
            'new_ticket_hash' => hash('sha256', 'TCK-SEC-006-ROTATED-TOKEN-FESTIVAL'),
            'status' => TicketResaleStateMachine::STATUS_SETTLED,
            'escrow_held_at' => now()->subDays(3),
            'settled_at' => now()->subDays(2),
        ]);
        SecondaryLedgerEntry::create([
            'entry_code' => 'LEDGER-'.Str::upper(Str::random(10)),
            'resale_id' => $resaleSettled6->id,
            'debit_account' => 'ESCROW_CLEARING',
            'credit_account' => 'SETTLED_DISTRIBUTION',
            'amount_idr' => 550000,
            'description' => 'Konservasi akuntansi tertutup pasar sekunder Festival Silver',
        ]);

        // 4. Kasus T1 7..11: Antrean Waitlist Prioritas (Waitlist Priority Governor)
        // Kasus T1.7: Waitlist Prioritas Tinggi (Fan Club + Poin Loyalitas Tinggi)
        TicketWaitlist::create([
            'waitlist_code' => 'WLT-'.Str::upper(Str::random(8)),
            'event_id' => $event->id,
            'zone_id' => 1,
            'user_id' => $buyers[1]->id,
            'status' => WaitlistStateMachine::STATUS_WAITING,
        ]);

        // Kasus T1.8: Waitlist Status OFFERED (Token 15 Menit Aktif)
        $w2 = TicketWaitlist::create([
            'waitlist_code' => 'WLT-'.Str::upper(Str::random(8)),
            'event_id' => $event->id,
            'zone_id' => 1,
            'user_id' => $buyers[2]->id,
            'offer_expires_at' => now()->addMinutes(12),
            'status' => WaitlistStateMachine::STATUS_OFFERED,
        ]);
        WaitlistReservation::create([
            'waitlist_id' => $w2->id,
            'token' => 'WLT-TKN-'.Str::upper(Str::random(12)),
            'offered_at' => now(),
            'expires_at' => now()->addMinutes(12),
            'status' => 'PENDING',
        ]);

        // Kasus T1.9: Waitlist Status CLAIMED (Tiket Berhasil Diambil)
        $w3 = TicketWaitlist::create([
            'waitlist_code' => 'WLT-'.Str::upper(Str::random(8)),
            'event_id' => $event->id,
            'zone_id' => 1,
            'user_id' => $buyers[3]->id,
            'offer_expires_at' => now()->subHours(1),
            'status' => WaitlistStateMachine::STATUS_CLAIMED,
        ]);
        WaitlistReservation::create([
            'waitlist_id' => $w3->id,
            'token' => 'WLT-TKN-'.Str::upper(Str::random(12)),
            'offered_at' => now()->subHours(2),
            'expires_at' => now()->subHours(1),
            'claimed_at' => now()->subHours(1)->addMinutes(10),
            'status' => 'CLAIMED',
        ]);

        // Kasus T1.10: Waitlist Status EXPIRED (Tidak Diambil Dalam 15 Menit)
        TicketWaitlist::create([
            'waitlist_code' => 'WLT-'.Str::upper(Str::random(8)),
            'event_id' => $event->id,
            'zone_id' => 1,
            'user_id' => $buyers[4]->id,
            'offer_expires_at' => now()->subMinutes(30),
            'status' => WaitlistStateMachine::STATUS_EXPIRED,
        ]);

        // Kasus T1.11: Waitlist Antrean Biasa Masih Menunggu
        TicketWaitlist::create([
            'waitlist_code' => 'WLT-'.Str::upper(Str::random(8)),
            'event_id' => $event->id,
            'zone_id' => 1,
            'user_id' => $buyers[5]->id,
            'status' => WaitlistStateMachine::STATUS_WAITING,
        ]);

        // 5. Kasus T1 12..14: Dynamic Bundling Packages & Components
        // Kasus T1.12: Paket All-in-One VIP Soundwave (Konser VIP + Suite Hotel + Shuttle Bandara)
        $pkg1 = DynamicBundlePackage::create([
            'package_code' => 'PKG-'.Str::upper(Str::random(8)),
            'event_id' => $event->id,
            'package_name' => 'Paket Soundwave All-in-One Luxury VIP',
            'base_price_idr' => 4500000,
            'floor_cost_idr' => 3500000,
            'final_price_idr' => 3825000,
            'surge_bps' => 0,
            'lead_time_discount_bps' => 1500, // 15% diskon
            'status' => DynamicBundlePackageStateMachine::STATUS_PUBLISHED,
        ]);
        DynamicBundleComponent::create([
            'bundle_package_id' => $pkg1->id,
            'component_type' => 'TICKET',
            'name' => 'Tiket Konser Nusantara VIP Platinum',
            'base_price_idr' => 2000000,
            'floor_cost_idr' => 1500000,
        ]);
        DynamicBundleComponent::create([
            'bundle_package_id' => $pkg1->id,
            'component_type' => 'HOTEL',
            'name' => 'Hotel Grand Deluxe 2 Malam (G7-K20)',
            'base_price_idr' => 2000000,
            'floor_cost_idr' => 1600000,
        ]);
        DynamicBundleComponent::create([
            'bundle_package_id' => $pkg1->id,
            'component_type' => 'SHUTTLE',
            'name' => 'Shuttle Bus VIP Bandara-ICE BSD (G7-K21)',
            'base_price_idr' => 500000,
            'floor_cost_idr' => 400000,
        ]);

        // Kasus T1.13: Paket Festival Duo + F&B Voucher
        $pkg2 = DynamicBundlePackage::create([
            'package_code' => 'PKG-'.Str::upper(Str::random(8)),
            'event_id' => $event->id,
            'package_name' => 'Paket Weekend Festival Duo Soundwave',
            'base_price_idr' => 1600000,
            'floor_cost_idr' => 1300000,
            'final_price_idr' => 1440000,
            'surge_bps' => 0,
            'lead_time_discount_bps' => 1000, // 10% diskon
            'status' => DynamicBundlePackageStateMachine::STATUS_PUBLISHED,
        ]);
        DynamicBundleComponent::create([
            'bundle_package_id' => $pkg2->id,
            'component_type' => 'TICKET',
            'name' => 'Dua Tiket Festival Gold',
            'base_price_idr' => 1400000,
            'floor_cost_idr' => 1100000,
        ]);
        DynamicBundleComponent::create([
            'bundle_package_id' => $pkg2->id,
            'component_type' => 'FNB',
            'name' => 'Voucher Bar & Lounge Festival Rp 200.000',
            'base_price_idr' => 200000,
            'floor_cost_idr' => 200000,
        ]);

        // Kasus T1.14: Paket Draft Bundle (Belum Dipublikasi)
        DynamicBundlePackage::create([
            'package_code' => 'PKG-'.Str::upper(Str::random(8)),
            'event_id' => $event->id,
            'package_name' => 'Paket Early Bird Soundwave 2027 (Draft)',
            'base_price_idr' => 2500000,
            'floor_cost_idr' => 1800000,
            'final_price_idr' => 2000000,
            'surge_bps' => 0,
            'lead_time_discount_bps' => 2000,
            'status' => DynamicBundlePackageStateMachine::STATUS_DRAFT,
        ]);

        // 6. Kasus T1.15: Scalper Bot Blacklist Log (Keamanan & Velocity Risk G7-K22)
        ScalperBlacklistLog::create([
            'user_id' => $scalperUser->id,
            'reason_code' => 'VELOCITY_SPIKE_AUTO_BLACKLIST',
            'detected_velocity_bps' => 140,
            'ip_address' => '103.145.22.88',
            'device_fingerprint' => 'bot-puppeteer-fingerprint-v1-hash998',
            'action_taken' => 'PERMANENT_BLACKLIST',
        ]);
    }
}
