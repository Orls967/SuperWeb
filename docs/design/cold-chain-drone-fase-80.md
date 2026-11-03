# Desain Teknis — Fase 80: Cold-Chain Blockchain Autonomous, Drone & Last-Mile Robotics (P1.3)

## 1. Ringkasan Eksekutif & Keputusan Arsitektur
Fase 80 menerapkan otomatisasi penegakan suhu logistik rantai dingin (cold-chain) berbasis telemetri IoT reefer yang terhubung langsung ke pembukuan buku besar (ledger) keuangan perusahaan, serta perencanaan dan dispatch misi drone last-mile otonom yang mematuhi zona larangan terbang (no-fly zone) dengan sertifikasi visibility graph dan pembuktian POD geohash kriptografis.

### Keputusan Kunci (D80-1 s.d. D80-4)
- **D80-1 (Profil Suhu & Episode Breach):**
  - Rentang suhu ideal disimpan dalam satuan 0,1 °C integer (`min_temp_deci`, `max_temp_deci`).
  - Episode pelanggaran dihitung mulai saat suhu pertama kali keluar rentang hingga pembacaan pertama kembali normal: `durasi = t_akhir − t_mulai`.
  - Breach sah jika `durasi > 600 detik` (10 menit). Durasi $\le 10$ menit (misal 9 menit atau tepat 10 menit) tidak memicu hold.
- **D80-2 (Idempotensi & Hash Chain):**
  - Satu record tahanan per pasangan `(leg_id, episode_id)`. Idempotent key: `lgx:temp_breach_hold:{leg_id}:{episode_id}`.
  - Event `lgx.temperature.breached.v1` dipancarkan tepat satu kali per episode breach.
  - Ringkasan pembacaan suhu disimpan dalam custody hash-chain shipment.
- **D80-3 (Posting Akuntansi Double-Entry):**
  - Tahan (breach):
    - **Debit:** `ap:carrier:{carrier_id}:IDR`
    - **Kredit:** `lgx:carrier_payable_on_hold:IDR`
  - Rilis tahanan:
    - **Debit:** `lgx:carrier_payable_on_hold:IDR`
    - **Kredit:** `ap:carrier:{carrier_id}:IDR`
- **D80-4 (Drone Routing & Visibility Graph):**
  - Batasan fisik drone: berat paket $\le 5.000$ gram, radius hub-tujuan $\le 15.000$ meter, panjang rute $\le$ jangkauan baterai.
  - Jalur penerbangan tidak boleh memotong INTERIOR zona larangan terbang (menyinggung tepi diizinkan).
  - Algoritma `Modules\Shared\Domain\Algorithms\Geo\VisibilityGraphRouter` membangun graf visibilitas antara Hub, Target, dan simpul-simpul no-fly zone, menyaring ruas yang memotong interior via `Geo\SegmentPolygonIntersection`, lalu mencari rute terpendek via Dijkstra dengan tie-break urutan simpul $(x, y)$ leksikografis terkecil.
  - Vektor prompt benchmark: Hub $(0, 0)$, Tujuan $(10.000, 0)$, No-Fly Zone $[(4.000, -1.000), (6.000, -1.000), (6.000, 1.000), (4.000, 1.000)]$ $\rightarrow$ rute: $(0,0) \rightarrow (4.000, -1.000) \rightarrow (6.000, -1.000) \rightarrow (10.000, 0)$, panjang tepat $10.246$ meter.
  - Proof of Delivery (POD) divalidasi via `Modules\Shared\Domain\Algorithms\Geo\Geohash` dengan panjang 9 karakter: $(-6.175392, 106.827153) \rightarrow$ `qqguygv1t`.

## 2. Struktur Data & Relasi
- `lgx_temperature_readings`: log telemetri sensor suhu per leg shipment (append-only).
- `lgx_temp_breach_holds`: entitas penahanan utang carrier (`held`, `released`).
- `lgx_drone_units`: armada drone aktif (kapasitas muatan gram, max range meter).
- `lgx_drone_missions`: riwayat & rencana misi terbang drone (`planned`, `in_flight`, `delivered`, `failed`).
- `lgx_no_fly_zones`: poligon batas wilayah larangan terbang (koordinat mikroderajat/meter).

## 3. Akun Pembukuan Terlibat
- `ap:carrier:{carrier_id}:IDR`: Liabilitas utang ongkos kirim carrier (Normal: Kredit).
- `lgx:carrier_payable_on_hold:IDR`: Akun penampungan liabilitas carrier yang dibekukan karena breach (Normal: Kredit).
Konservasi saldo: saldo `lgx:carrier_payable_on_hold:IDR` sama dengan $\sum \text{held\_amount}$ dari semua hold dengan status `held`.

## 4. Audit & Verifikasi Dua Sumber
Perintah `php artisan lgx:audit-coldchain`:
1. Memvalidasi bahwa setiap pembacaan suhu konsisten dalam rantai logistik.
2. Memverifikasi rekonsiliasi saldo buku besar `lgx:carrier_payable_on_hold:IDR` sama persis dengan total nominal utang yang berstatus `held` di tabel `lgx_temp_breach_holds`.
3. Memastikan fixture korupsi data buatan (`LogisticsColdChainCorruptionFixture`) terdeteksi dan menggagalkan audit.
