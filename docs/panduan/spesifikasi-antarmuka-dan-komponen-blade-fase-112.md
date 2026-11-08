# Spesifikasi Antarmuka & Komponen Blade — Fase 112: Global Loyalty & Travel Pass

## 1. Ikhtisar Standar UI (Poles UI 35%)
Sesuai dengan standar ketat W8, seluruh antarmuka Fase 112 dibangun dengan prinsip desain kelas enterprise:
- **Framework Tampilan:** Tailwind CSS dengan utilitas tipografi, flexbox, dan grid responsif.
- **Interaktivitas:** Alpine.js untuk reaktivitas lokal (modal konfirmasi, kalkulator dinamis, slider okupansi, radar chart SVG).
- **Aksesibilitas:** Label ARIA semantik, kontras warna WCAG AA, dan dukungan navigasi keyboard.

## 2. Katalog Halaman Tampilan (Blade Views)
1. `travel_pass_global_dashboard.blade.php`: Dasbor metrik eksekutif program loyalitas global, distribusi anggota, dan pergerakan liabilitas.
2. `award_night_dynamic_booking_portal.blade.php`: Portal pemesanan kamar dengan poin reward yang menampilkan kurva harga dinamis dan kalender ketersediaan.
3. `partner_point_exchange_hub.blade.php`: Hub konversi poin ke miles maskapai dan voucher transportasi mitra dengan simulasi kurs instan.
4. `loyalty_liability_breakage_control_center.blade.php`: Pusat kendali audit keuangan untuk pemantauan batch FIFO, kedaluwarsa, dan pembukuan pendapatan breakage.
5. `travel_personalization_recommendation_board.blade.php`: Papan kendali rekomendasi perjalanan berbasis profil preferensi 360° tamu.
6. `hotel_alliance_partner_settlement_view.blade.php`: Rekonsiliasi bulanan penyelesaian utang/piutang aliansi bilateral.
7. `travel_pass_member_portal.blade.php`: Portal keanggotaan mandiri tamu untuk melihat QR paspor, riwayat malam, dan katalog penukaran.
8. `loyalty_audit_reconciliation_center.blade.php`: Pusat audit dan verifikasi invarian matematika seluruh akun buku besar loyalitas.

## 3. Komponen Blade Reusable
1. `components/tier_progress_radar_gauge.blade.php`: Indikator visual progres kenaikan tier keanggotaan.
2. `components/award_calendar_heatmap.blade.php`: Matriks kalender warna-warni tarif poin per malam.
3. `components/points_expiry_fifo_timeline.blade.php`: Garis waktu penuaan batch poin dan estimasi kedaluwarsa.
4. `components/partner_conversion_rate_card.blade.php`: Kartu simulasi nilai tukar poin terhadap unit mitra.
5. `components/breakage_waterfall_chart.blade.php`: Grafik visual alur liabilitas vs breakage.
6. `components/personalization_offer_card.blade.php`: Kartu penawaran bundle perjalanan yang dipersonalisasi.
7. `components/alliance_netting_settlement_row.blade.php`: Baris tabel penyelesaian netting pembayaran aliansi.
8. `components/travel_pass_nfc_card_preview.blade.php`: Tampilan visual kartu keanggotaan digital mewah dengan efek hologram.
9. `components/points_liability_ledger_summary.blade.php`: Rekapitulasi akun buku besar liabilitas dan biaya loyalitas.

