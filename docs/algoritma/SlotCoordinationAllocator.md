# Algoritma: SlotCoordinationAllocator (A1)

## 1. Lokasi Kode & Arsitektur
- **Kelas Algoritma:** `Modules\Shared\Domain\Algorithms\Aviation\SlotCoordinationAllocator`
- **Sertifikat:** `Modules\Shared\Domain\Algorithms\Aviation\SlotCoordinationCertificate`
- **Verifier:** `Modules\Shared\Domain\Algorithms\Aviation\SlotCoordinationCertificateVerifier`
- **Hasil:** `Modules\Shared\Domain\Algorithms\Aviation\SlotCoordinationResult`
- **Pillar/Lini:** Lini 26 (Aviasi, Fase 177)
- **Kompleksitas Waktu:** $O(N \log N + M \log M)$ di mana $N$ adalah jumlah permintaan pendatang baru dan $M$ adalah total permintaan.
- **Kompleksitas Ruang:** $O(N + M)$ untuk antrean alokasi dan penampung waitlist.

## 2. Intuisi Bisnis & Dasar Regulasi
Mengacu pada Worldwide Airport Slot Guidelines (WASG) yang diterbitkan bersama oleh IATA, ACI, dan WWACG:
1. **Aturan Hak Historis 80/20 (Grandfather Rights):** Maskapai penerbangan yang telah mengoperasikan slot bandara terjadwal pada musim sebelumnya berhak mempertahankan slot tersebut pada musim yang sama berikutnya (*historic precedence*), dengan syarat tingkat pemanfaatan riil minimal 80% (8.000 bps). Jika realisasi pemakaian di bawah 80%, seluruh slot historis dikembalikan (*returned*) ke kolam slot umum (*slot pool*).
2. **Kolam Slot Tersedia (*Available Pool*):** Dihitung sebagai selisih antara kapasitas teknis per jam bandara dan total slot historis yang berhasil dipertahankan.
3. **Pencadangan Pendatang Baru (*New Entrant Rule*):** Sebanyak 50% dari kolam slot (dibulatkan ke atas / ceil) wajib dialokasikan secara prioritas kepada maskapai berstatus pendatang baru (*new entrants*) guna mencegah monopoli rute oleh maskapai petahana (*incumbent*).
4. **Antrean Deterministik:** Permintaan pendatang baru dilayani sesuai urutan stempel waktu pengajuan (*request timestamp*). Sisa kolam yang tidak terpakai dikembalikan ke antrean umum untuk diperebutkan oleh seluruh maskapai (petahana dan sisa pendatang baru) berdasarkan waktu pengajuan secara adil dan transparan.

## 3. Rumus Matematis & Spesifikasi Logika
$$\text{Retained}(op) = \begin{cases} \text{Held}(op), & \text{jika } \text{UsageBps}(op) \ge 8.000 \\ 0, & \text{jika } \text{UsageBps}(op) < 8.000 \end{cases}$$

$$\text{Pool} = \max\left(0, \text{Capacity} - \sum_{op} \text{Retained}(op)\right)$$

$$\text{NewEntrantReserve} = \left\lceil 0,5 \times \text{Pool} \right\rceil$$

Jika kuota kolam habis, permintaan yang belum terpenuhi dimasukkan ke dalam daftar tunggu (*waitlist*) dengan status `waitlisted`.

## 4. Contoh Hitung Langkah Demi Langkah (Vektor Prompt)
**Parameter Input:**
- Kapasitas bandara: 10 slot.
- Data historis:
  - Maskapai A: 4 slot, realisasi pemakaian 90% (9.000 bps).
  - Maskapai B: 3 slot, realisasi pemakaian 80% (8.000 bps).
  - Maskapai C: 2 slot, realisasi pemakaian 79% (7.900 bps).
- Permintaan baru:
  - N1 (Pendatang Baru, minta 2 slot, waktu $t=1$).
  - A (Petahana, minta 1 slot tambahan, waktu $t=2$).
  - D (Petahana, minta 2 slot, waktu $t=3$).
  - N2 (Pendatang Baru, minta 1 slot, waktu $t=4$).

**Langkah Evaluasi:**
1. Evaluasi historis:
   - A: $90\% \ge 80\% \to$ dipertahankan 4 slot.
   - B: $80\% \ge 80\% \to$ dipertahankan 3 slot.
   - C: $79\% < 80\% \to$ gugur (0 slot), 2 slot dikembalikan ke kolam.
   - Total slot dipertahankan $= 4 + 3 = 7$ slot.
2. Hitung kolam dan cadangan:
   - Kolam slot $= 10 - 7 = 3$ slot.
   - Cadangan pendatang baru $= \lceil 3 \times 50\% \rceil = \lceil 1,5 \rceil = 2$ slot.
3. Alokasi pendatang baru (Cadangan = 2):
   - N1 ($t=1$, minta 2) $\to$ dilayani penuh 2 slot. Sisa cadangan $= 0$.
   - N2 ($t=4$, minta 1) $\to$ cadangan habis, masuk ke antrean umum.
4. Alokasi antrean umum (Sisa Kolam $= 3 - 2 = 1$ slot):
   - Antrean berdasar waktu pengajuan:
     - A ($t=2$, minta 1)
     - D ($t=3$, minta 2)
     - N2 ($t=4$, minta 1)
   - A ($t=2$, minta 1) $\to$ dilayani penuh 1 slot. Sisa kolam $= 0$.
   - D ($t=3$, minta 2) $\to$ kolam habis, masuk waitlist (2 slot).
   - N2 ($t=4$, minta 1) $\to$ kolam habis, masuk waitlist (1 slot).
5. Hasil Akhir Alokasi:
   - Alokasi tambahan: N1 = 2 slot, A = +1 slot, D = 0 slot, N2 = 0 slot.
   - Total slot dipegang: A = 5, B = 3, N1 = 2, C = 0, D = 0, N2 = 0.
   - Waitlist: D = 2 slot, N2 = 1 slot.
   - Sisa kolam $= 0$ slot. Persis sesuai vektor prompt.

## 5. Sertifikat & Kondisi Kegagalan
Sertifikat `SlotCoordinationCertificate` menyimpan snapshot ringkasan alokasi dan divalidasi oleh `SlotCoordinationCertificateVerifier`. Verifikasi melempar `InvalidCertificateException` bila:
1. Total slot dialokasikan melebihi kapasitas bandara.
2. Ukuran kolam tidak sama dengan kapasitas dikurangi total slot historis dipertahankan.
3. Sisa kolam bernilai negatif.

