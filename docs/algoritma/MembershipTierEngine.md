# Algoritma V7: MembershipTierEngine

## 1. Intuisi Bisnis & Masalah
Beach club pesisir dan kelab malam premium mengandalkan tamu loyal untuk mempertahankan tingkat okupansi meja VIP dan konsumsi botol bernilai tinggi. Untuk mendorong retensi pelanggan, sistem keanggotaan membagi tamu ke dalam tiga tingkat eksklusif: `Sun`, `Moon`, dan `Infinity`.

Masalah yang sering muncul dalam sistem keanggotaan konvensional:
1. **Penurunan Tingkat Mendadak (*Sudden Downgrade Shock*):** Tamu VIP yang sedikit di bawah target belanja pada akhir tahun langsung diturunkan statusnya, memicu kekecewaan dan hilangnya retensi pelanggan.
2. **Ketiadaan Verifikasi Kehadiran Fisik:** Member yang hanya belanja daring namun tidak pernah hadir di venue mendapatkan status yang sama dengan pengunjung setia.
3. **Penyalahgunaan Fasilitas VIP:** Hak kompartemen loker botol pribadi (*bottle storage locker*) diberikan secara sembarangan kepada member non-VIP sehingga menimbulkan kekurangan kompartemen bagi pelanggan bernilai tertinggi.

Algoritma V7 **`MembershipTierEngine`** menyelesaikan masalah ini dengan mengombinasikan akumulasi belanja 365 hari bergulir, kehadiran fisik turnstile, aturan masa tenggang (*anniversary grace period* 80%), dan alokasi loker bersertifikat.

---

## 2. Kriteria Kualifikasi & Rumus Evaluasi
Diberikan parameter masukan:
- $T_{\text{current}} \in \{\text{'sun'}, \text{'moon'}, \text{'infinity'}\}$: Tingkat keanggotaan saat ini.
- $S_{365} \in \mathbb{N}$: Total belanja terverifikasi dalam 365 hari terakhir (`spend_365_days_idr`).
- $V_{365} \in \mathbb{N}$: Jumlah pemindaian masuk gerbang turnstile dalam 365 hari terakhir (`visits_365_days`).
- $E_{\text{anniv}} \in \{\text{true}, \text{false}\}$: Apakah evaluasi dilakukan pada tanggal ulang tahun keanggotaan tahunan.

### Tahap 1: Evaluasi Tingkat Natural (Natural Tier)
$$\text{Natural Tier} = \begin{cases}
\text{'infinity'} & \text{jika } S_{365} \ge 75.000.000 \text{ DAN } V_{365} \ge 24 \\
\text{'moon'} & \text{jika } S_{365} \ge 20.000.000 \text{ ATAU } V_{365} \ge 12 \\
\text{'sun'} & \text{lainnya}
\end{cases}$$

### Tahap 2: Aturan Perlindungan Masa Tenggang (Anniversary Grace Period)
Jika $\text{rank}(\text{Natural Tier}) < \text{rank}(T_{\text{current}})$:
- Jika $E_{\text{anniv}} = \text{false}$: Tingkat keanggotaan dipertahankan sama dengan $T_{\text{current}}$ (penurunan tingkat hanya terjadi saat evaluasi tahunan).
- Jika $E_{\text{anniv}} = \text{true}$:
  - Hitung ambang batas 80%:
    $$S_{\text{threshold}} = \begin{cases} 75.000.000 & \text{jika } T_{\text{current}} = \text{'infinity'} \\ 20.000.000 & \text{jika } T_{\text{current}} = \text{'moon'} \end{cases}$$
    $$S_{\text{80pct}} = \lfloor (S_{\text{threshold}} \times 8) / 10 \rfloor$$
  - Jika $S_{365} \ge S_{\text{80pct}}$: Anggota diberikan status `grace` selama 30 hari dan tingkat keanggotaan tetap dipertahankan.
  - Jika $S_{365} < S_{\text{80pct}}$: Tingkat keanggotaan diturunkan tepat satu tingkat ke bawah:
    $$\text{Resolved Tier} = \begin{cases} \text{'moon'} & \text{jika } T_{\text{current}} = \text{'infinity'} \\ \text{'sun'} & \text{jika } T_{\text{current}} = \text{'moon'} \end{cases}$$

### Tahap 3: Hak Kompartemen Loker Botol VIP
$$\text{Eligible For Bottle Locker} = (\text{Resolved Tier} = \text{'infinity'})$$

### Tahap 4: Kalkulasi Poin Loyalitas (Lifestyle Points)
$$\text{Base Points} = \lfloor \text{Spend IDR} / 10.000 \rfloor$$
$$\text{Multiplier Bps} = \begin{cases} 200\text{ bps (2.0x)} & \text{jika } \text{Tier} = \text{'infinity'} \\ 150\text{ bps (1.5x)} & \text{jika } \text{Tier} = \text{'moon'} \\ 100\text{ bps (1.0x)} & \text{jika } \text{Tier} = \text{'sun'} \end{cases}$$
$$\text{Earned Points} = \left\lfloor \frac{\text{Base Points} \times \text{Multiplier Bps}}{100} \right\rfloor$$

---

## 3. Pembuktian Langkah Demi Langkah Vektor Acuan
**Kasus 1: Member Infinity Memenuhi Syarat Penuh**
- Masukan: $S_{365} = 80.000.000\text{ IDR}$, $V_{365} = 25\text{ kali}$, $E_{\text{anniv}} = \text{false}$.
- $S_{365} \ge 75.000.000$ dan $V_{365} \ge 24 \implies \text{Natural Tier} = \mathbf{'infinity'}$.
- $\text{Eligible Bottle Locker} = \mathbf{true}$.
- Transaksi Rp 1.500.000 di tier Infinity: $\lfloor 1.500.000 / 10.000 \rfloor \times 2 = \mathbf{300\text{ PTS}}$.

**Kasus 2: Member Infinity Mendapatkan Grace Period saat Anniversary**
- Masukan: $T_{\text{current}} = \text{'infinity'}$, $S_{365} = 62.000.000\text{ IDR}$, $V_{365} = 10\text{ kali}$, $E_{\text{anniv}} = \text{true}$.
- Ambang 80% dari Rp 75.000.000 adalah Rp 60.000.000.
- $S_{365} = 62.000.000 \ge 60.000.000 \implies \text{Status} = \mathbf{inGracePeriod = true}$, $\text{Resolved Tier} = \mathbf{'infinity'}$.
