# Definition of Ready (DoR) — Fase 117: Guest Experience AI, Biometric Entry & Crowd Safety

**Modul Pemilik:** `Venue` (`modules/Venue/`, sub-item 117.1 s/d 117.7)  
**Linis Bisnis:** KONSEP §10F, Lini 15  
**Prasyarat:** Fase 89, Fase 90, dan Fase 116 Merged ke Master  
**Pelaksana:** Lead Cryptographer, Lead AI Systems Architect & Safety Engineer `[G7-P5]`  
**Target Kualitas:** Ambang Keras W8 ($B1 \ge 6.000$, $B2 \ge 35\% \times B1 \ge 2.100$, $B3 \ge 1.500$)  

---

## 1. Latar Belakang & Tujuan Bisnis

Operasional venue skala besar (festival musik, beach club, stadion, arena konser) menghadapi dua tantangan eksistensial:
1. **Keamanan Kerumunan & Keselamatan Nyawa (Crowd Safety):** Risiko penumpukan massa berlebih (crowd surge / crush) di pintu masuk utama, bar, dan lorong sempit (chokepoints) yang berpotensi fatal tanpa sistem prediksi kepadatan real-time.
2. **Efisiensi Gerbang & Pengalaman Tamu (Guest Experience):** Antrean tiket kertas/ponsel yang lambat menyebabkan bottleneck di turnstile, waktu tunggu bar (Wait Per Service / WPS) yang panjang menurunkan kepuasan, dan kurangnya personalisasi penawaran langsung (on-site exclusive offers).

Fase 117 menghadirkan arsitektur pertahanan kerumunan deterministik berlapis (deterministic defense-in-depth) yang menggabungkan:
- **Biometric Turnstile Access (Face-ID):** Otentikasi wajah nir-sentuh dengan verifikasi liveness challenge-response anti-spoofing, di mana fitur biometrik dipetakan ke hash kriptografis bergaram yang tak dapat direkonstruksi (privacy-preserving one-way hashing).
- **Predictive Crowd Safety Engine:** Model proyeksi linier dan tren eksponensial 15 menit ke depan untuk mendeteksi potensi lonjakan kepadatan per meter persegi sebelum terjadi, secara otomatis memicu pembukaan gerbang sekunder dan pengalihan aliran massa.
- **Wait Per Service (WPS) & Antrean Bar:** Sensor laju layanan kasir dan bar yang memantau skor kepuasan dan secara otonom memanggil reinforcement bartender saat rasio antrean memburuk.
- **Personalized On-Site AI Router:** Pengiriman penawaran F&B eksklusif kontekstual ke gawai tamu VIP dengan batasan margin ketat dan pelindung anti-spam/fatigue.

---

## 2. Kriteria Penerimaan Wajib (Acceptance Criteria)

### AC-117.1: Biometric Face-ID Door Entry & Privacy Vault
- Tamu terdaftar dapat melewati turnstile otomatis tanpa tiket fisik/ponsel.
- Verifikasi liveness check wajib lolos tantangan acak deterministik (kedipan, senyum, orientasi kepala) dengan skor kepercayaan $\ge 8.500$ bps (85%).
- Vektor fitur biometrik mentah **DILARANG KERAS** disimpan di basis data. Hanya hash kriptografis bergaram HMAC-SHA256 (`biometric_template_hash`, 64 hex characters) yang disimpan.
- Tamper-evident matching: Upaya spoofing atau perbedaan templat wajah wajib memicu penolakan seketika dengan log audit anti-spoofing.
- Tamu memiliki hak pencabutan (right to revoke) biometrik sesuai standar privasi GDPR/UU PDP.

### AC-117.2: Peta Okupansi & Live Heatmap SVG Real-Time
- Setiap transaksi scan turnstile masuk dan keluar mengkalkulasi agregat okupansi riil per zona (`current_headcount`).
- Kerapatan zona dihitung sebagai $\text{Density} = \frac{\text{Headcount}}{\text{Area } m^2}$.
- Render SVG Live Heatmap interaktif pada dashboard admin dan operator gerbang dengan adaptasi warna standar keselamatan internasional:
  - Hijau: Okupansi $< 70\%$ ($\le 2 \text{ orang/m}^2$) — Kondisi normal.
  - Kuning: Okupansi $70\% - 85\%$ ($2 - 3 \text{ orang/m}^2$) — Waspada.
  - Oranye: Okupansi $85\% - 95\%$ ($3 - 4 \text{ orang/m}^2$) — Kepadatan tinggi.
  - Merah: Okupansi $\ge 95\%$ ($> 4 \text{ orang/m}^2$) — Gerbang dibatasi otomatis (`GATE_RESTRICTED`).
  - Flash Merah/Hitam: Kondisi darurat evakuasi (`EVACUATION_STANDBY`).
- Query budget kalkulasi agregat peta kerumunan $\le 10$ query SQL per siklus refresh.

### AC-117.3: Crowd Safety AI & Otomasi Gerbang Sekunder
- Algoritma `CrowdDensitySurgePredictor` (V14) memproyeksikan headcount 15 menit ke depan:
  $$\hat{H}_{t+15} = H_t + 15 \times \left( \alpha \cdot \frac{d H_{in}}{dt} - \beta \cdot \frac{d H_{out}}{dt} \right)$$
- Jika proyeksi $\hat{H}_{t+15} \ge \text{Capacity Limit}$, sistem secara deterministik menghasilkan rekomendasi `RESTRICT_ACCESS` atau `OPEN_SECONDARY_GATES`.
- State machine insiden kerumunan (`CrowdIncidentStateMachine`) mengunci alur gerbang utama dan merekam otorisasi operator keselamatan (`crowd_safety_officer`).

### AC-117.4: Guest Experience Scoring (WPS & Bar Queue)
- Pengukuran waktu tunggu per layanan (Wait Per Service / WPS) di setiap titik bar dan F&B stasiun:
  $$\text{WPS Score} = \max\left(0, 100 - \frac{\text{Wait Time (menit)} \times 100}{\text{Max Tolerable Wait (15 menit)}}\right)$$
- Saat WPS Score $< 60$ pada zona dengan kapasitas $> 80\%$, sistem otonom menerbitkan sinyal permintaan tambahan tenaga kerja kasir/bartender via kontrak HCM Shift Bounty.
- Agregasi NPS pasca-acara digabungkan ke kartu skor brand venue (`BrandScorecard`).

### AC-117.5: Personalized On-Site Contextual Offers
- Pemicu promo kontekstual saat tamu VIP terdeteksi berada di zona tertentu.
- Pelindung anti-spam (Anti-Fatigue Filter): Maksimal 1 promo per tamu per jam, maksimal 3 promo per malam per acara.
- Idempotency & De-duplikasi: Pengiriman promo ganda dengan parameter dan jendela waktu yang sama wajib mengembalikan ID promo awal yang identik tanpa membuat entri baru.
- Margin Guardrail: Diskon promo tidak boleh menurunkan harga jual di bawah harga pokok penjualan (HPP/COGS).

### AC-117.6: Acceptance Tests Terpadu
- Maksimal 5 acceptance tests pada `tests/Acceptance/VenueCrowd/VenueCrowdAcceptanceTest.php` yang memverifikasi seluruh AC-117.1 s/d AC-117.5.

### AC-117.7: Audit Kepatuhan & Fixture Sabotase
- Command audit `php artisan venue:audit-crowd` memeriksa:
  1. Konsistensi scan masuk vs keluar terhadap headcount zona riil (Zero Headcount Drift).
  2. Ketunggalan templat hash biometrik aktif per pengguna (Zero Biometric Collisions).
  3. Integritas log insiden kerumunan dan ketiadaan status gerbang terblokir yang terbengkalai.
- Fixture sabotase `VenCrowdAuditCorruptionFixture` terdaftar di `AuditCommandRegistry`, memicu kode keluar 1 saat dirusak, dan pulih 100% setelah perbaikan.

---

## 3. Matriks Kepatuhan Arsitektur & Larangan Keras

1. **Aturan Larangan Float (A3):** Seluruh metrik kuantitas, rasio basis poin (bps), kapasitas, koordinat peta SVG, dan waktu integer detik/menit. Dilarang tipe float untuk nilai finansial.
2. **Aturan Larangan Idempotency Key Acak (A4):** Semua token insiden dan promo menggunakan formulasi deterministik `hash_hmac` atau kode berurutan `INC-{event_id}-{zone_id}-{sequence}`.
3. **Aturan Impor Modul Lain (A1 & A2):** Tidak ada impor langsung ke `Modules\{Lain}\Domain\*`. Interaksi lintas modul hanya melalui Contracts (`Modules\Security\Contracts`, `Modules\Core\Contracts`, `Modules\Healthcare\Contracts`).
4. **Poles UI (B2):** Minimal 35% dari B1 baris kode Blade responsif 375px mobile, dukungan tema gelap Tailwind, SVG Interactive Heatmap interaktif, ARIA accessibility.

