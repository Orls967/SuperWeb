# Algoritma H14: BlockAllotmentReleaser

## 1. Definisi & Ruang Lingkup
Algoritma `BlockAllotmentReleaser` bertugas mengelola siklus hidup alokasi kamar blok untuk delegasi konferensi, exhibitor expo, dan rombongan pernikahan. Algoritma ini memastikan optimalisasi ketersediaan kamar hotel dengan melepaskan kamar yang tidak terisi nama tamu (*unallocated allotment*) kembali ke saluran distribusi publik secara terjadwal.

## 2. Struktur Data Masukan (Input)
- `propertyId` (string): Pengenal properti hotel.
- `blockId` (string): ID blok pemesanan MICE.
- `initialRooms` (int): Jumlah total kamar yang dikomitmenkan di awal kontrak ($K_0$).
- `currentAllocatedRooms` (int): Jumlah kamar blok yang saat ini masih ditahan ($K_t$).
- `confirmedPickupRooms` (int): Jumlah kamar yang sudah diisi data tamu (*rooming list*) definitif ($P_t$).
- `daysUntilEvent` (int): Jumlah hari tersisa sebelum tanggal check-in perdana grup ($\Delta$).
- `customCutoffRates` (array opsional): Persentase pelepasan kustom untuk H-30, H-14, dan H-7.

## 3. Aturan Bisnis & Logika Eksekusi
1. Validasi Masukan:
   - Pastikan $P_t \le K_t \le K_0$.
   - Jika $\Delta < 0$, event telah berlangsung, tidak ada pelepasan inventori yang dilakukan.
2. Penentuan Tingkat Pelepasan ($\alpha_t$):
   - $\Delta > 30$: $\alpha_t = 0.0$ (Masa proteksi penuh untuk organizer).
   - $14 < \Delta \le 30$: $\alpha_t = 0.25$ (Cutoff 1: pelepasan 25% dari sisa un-picked).
   - $7 < \Delta \le 14$: $\alpha_t = 0.50$ (Cutoff 2: pelepasan 50% dari sisa un-picked).
   - $\Delta \le 7$: $\alpha_t = 1.00$ (Cutoff 3 / Final: 100% sisa un-picked dilepaskan ke inventori publik).
3. Komputasi Pelepasan:
   - $U_t = K_t - P_t$ (Kamar un-picked).
   - Kamar dilepas: $R_t = \lfloor \alpha_t \times U_t \rfloor$.
   - Kamar blok tersisa: $K_{t+1} = K_t - R_t$.
4. Penerbitan Sertifikat:
   - Algoritma memproduksi `BlockAllotmentReleaseCertificate` yang berisi properti, ID blok, hari tersisa, kamar awal, kamar dilepas, kamar tersisa, dan hash SHA-256 verifikasi.

## 4. Struktur Output
```json
{
  "property_id": "HTL-BALI-01",
  "block_id": "BLK-MICE-2026-001",
  "days_until_event": 14,
  "cutoff_tier": "H14",
  "confirmed_pickups": 60,
  "unpicked_before": 40,
  "rooms_released_to_public": 20,
  "remaining_contracted_rooms": 80,
  "certificate_signature": "a6b4..."
}
```

## 5. Pertimbangan Keamanan & Konkurensi
- Eksekusi pelepasan kamar blok dijalankan di dalam transaksi database dengan isolasi serializable atau pessimistic row locking pada baris `htl_room_blocks` untuk menghindari kondisi balapan (*race condition*) saat tamu sedang melakukan self-registration.
