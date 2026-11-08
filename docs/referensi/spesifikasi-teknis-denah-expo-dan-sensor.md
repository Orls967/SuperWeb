# Spesifikasi Teknis: Pemetaan Grid Denah Booth & Integrasi IoT Sensor Kepadatan Pameran

## 1. Sistem Koordinat & Matriks Denah Hall Konvensi
Sistem denah pameran digital menggunakan representasi ruang dua dimensi berbasis sistem koordinat kartesius diskrit $(X, Y)$ dengan resolusi grid 1 meter per unit:
- Setiap titik $(x, y)$ pada denah aula (*convention exhibition hall*) dapat menampung satu unit sel modular berukuran $1\text{m} \times 1\text{m}$.
- Stan pameran standar berukuran $3\text{m} \times 3\text{m}$ didefinisikan sebagai persegi panjang dengan koordinat kiri-atas $(X_{\text{origin}}, Y_{\text{origin}})$ dan ukuran $(\Delta X = 3, \Delta Y = 3)$.
- Batas keamanan lorong utama (*primary aisle*) antar-stan diwajibkan selebar minimal 3 meter untuk evakuasi darurat, sedangkan lorong sekunder (*secondary aisle*) minimal 2 meter.

```
       Y
       ▲
    15 ┼   [ Hall Entrance A ]  ───── Gate Sensor #1 (Inbound/Outbound)
    14 ┼
    12 ┼   ┌──────────┐     ┌──────────┐     ┌──────────┐
    10 ┼   │ Booth A1 │     │ Booth A2 │     │ Booth A3 │
     8 ┼   │ (3m x 3m)│     │ (3m x 3m)│     │ (3m x 3m)│
     6 ┼   └──────────┘     └──────────┘     └──────────┘
     4 ┼   ═════════════════ Aisle 1 (3m Wide) ════════════════
     2 ┼   ┌───────────────────────────┐     ┌──────────┐
     0 ┼   │ Island Space Only Booth B1│     │ Booth B2 │
       └───┴───────────────────────────┴─────┴──────────┴────► X
           0   2   4   6   8  10  12  14  16  18  20  22  24
```

---

## 2. Model Data Penempatan Stan (Booth Collision Detection)
Dua stan pameran $B_1$ dan $B_2$ dinyatakan tidak tumpang tindih (*non-overlapping*) jika dan hanya jika berlaku kondisi ortogonal:
$$(X_1 + W_1 \le X_2) \lor (X_2 + W_2 \le X_1) \lor (Y_1 + H_1 \le Y_2) \lor (Y_2 + H_2 \le Y_1)$$
Di mana:
- $(X_k, Y_k)$ adalah koordinat sudut kiri bawah stan $k$.
- $W_k$ adalah lebar stan pada sumbu $X$.
- $H_k$ adalah panjang stan pada sumbu $Y$.

Sistem melarang penyimpanan pemesanan stan jika terjadi pelanggaran tumpang tindih (*collision constraint violation*) atau jika stan melanggar garis batas lorong evakuasi darurat (*fire evacuation pathway*).

---

## 3. Protokol Sensor Optik IoT Arus Pengunjung (Footfall Sensor Protocol)
1. **Perangkat Keras Sensor:**
   - Sensor gerbang menggunakan kamera stereoskopik optik 3D dengan pemrosesan tepi (*Edge AI*) yang membedakan antara manusia, troli barang, dan bayangan pencahayaan.
   - Sensor mengirimkan paket data telemetri setiap interval 60 detik melalui protokol MQTT terenkripsi TLS 1.3 ke broker pesan hotel.
2. **Format Payload Telemetri Sensor:**
```json
{
  "sensor_id": "IOT-GATE-NORTH-01",
  "hall_id": "HALL-NUSANTARA-A",
  "timestamp_utc": "2026-11-08T10:00:00Z",
  "inbound_count": 42,
  "outbound_count": 18,
  "net_flow": 24,
  "dwell_time_avg_seconds": 185.4,
  "device_battery_percent": 98.5
}
```
3. **Agregasi Metrik ROI Sponsor:**
   - Total estimasi pengunjung unik per stan dihitung berdasarkan kedekatan koordinat $(X_{\text{booth}}, Y_{\text{booth}})$ terhadap kepadatan arus pejalan kaki:
     $$\text{Estimated Visitors} = \sum_{\text{interval}} (\text{Aisle Footfall}) \times \gamma_{\text{attraction\_index}}$$
   - Indeks daya tarik stan ($\gamma$) berkisar antara $0.15$ untuk stan standar hingga $0.45$ untuk stan pulau terbuka dengan promosi interaktif.
