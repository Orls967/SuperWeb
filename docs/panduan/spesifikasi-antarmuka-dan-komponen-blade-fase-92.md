# Spesifikasi Antarmuka Pengguna & Komponen Blade — Modul Hotel Fase 92

## 1. Ikhtisar Desain & Standar Desain Sistem
Seluruh antarmuka pengguna pada modul Perhotelan Fase 92 dibangun menggunakan framework styling Tailwind CSS modern, komponen reaktif ringan Alpine.js, dan arsitektur Blade Components Laravel. 

Antarmuka dirancang untuk memenuhi standar ketat *Poles UI 35%* dengan menyediakan panel visual interaktif, kalkulator langsung di browser (*live simulation*), indikator status kontras tinggi, dan tata letak responsif untuk tablet operasional front-desk maupun layar lebar ruang kontrol GM.

---

## 2. Inventori Komponen UI Reusable

```
+---------------------------------------------------------------------------------+
|                        INVENTORI KOMPONEN BLADE FASE 92                         |
+------------------------------------+--------------------------------------------+
| Nama Komponen Blade                | Peran & Fungsionalitas Utama               |
+------------------------------------+--------------------------------------------+
| city_ledger_aging_table.blade.php  | Matriks tabel umur piutang AR korporat     |
| spa_therapist_roster_calendar      | Visualisasi kalender jadwal terapis spa    |
| package_vendor_settlement_ledger   | Rekonsiliasi real-time pelepasan escrow    |
| timeshare_rental_yield_calculator  | Kalkulator live simpanan dividen investor  |
| guest_rfm_radar_card.blade.php     | Kartu visual metrik RFM & profil preferensi|
| banquet_floor_plan_builder         | Layout 2D visual denah meja banquet & BEO  |
| dispute_chargeback_modal           | Modal interaktif isolasi sengketa folio    |
| folio_split_card.blade.php         | Kartu ringkasan sub-folio multi-pembayar   |
| package_milestone_tracker          | Stepper status pemenuhan voucher wisata    |
| timeshare_calendar_grid            | Grid 52-minggu kepemilikan hak guna vila   |
| banquet_recipe_bom_row             | Baris kalkulasi HPP resep dapur jamuan     |
| loyalty_tier_badge.blade.php       | Lencana status keanggotaan tamu hotel      |
+------------------------------------+--------------------------------------------+
```

---

## 3. Prinsip Ergonomi Antarmuka Front Desk
1. **Kecepatan Tindakan Kasir (One-Click Routing):** Operator kasir dapat memindahkan item baris tagihan dari folio personal ke sub-folio korporat hanya dengan memilih dropdown tanpa perlu memuat ulang seluruh halaman (*no full-page reload*).
2. **Pemberitahuan Audit Saldo Nol yang Jelas:** Tombol checkout terkunci secara visual dengan penanda merah menyala bila saldo belum tepat Rp 0, dan otomatis berubah menjadi hijau cerah dengan ikon centang ketika balance terpenuhi.
3. **Aksesibilitas & Kontras Warna:** Mematuhi pedoman WCAG 2.1 AA dengan rasio kontras teks minimal 4.5:1 terhadap latar belakang gelap maupun terang.
