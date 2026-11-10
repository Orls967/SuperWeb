# Katalog Galat Domain: Perbendaharaan & Keuangan Grup

Dokumen ini mendokumentasikan seluruh pengecualian (*exceptions*), kode galat, penyebab, dan langkah mitigasi untuk domain Treasury.

---

## Daftar Galat Domain Treasury (Fase 48)

### 1. `INVALID_RATE`
- **Tipe Exception**: `InvalidArgumentException`
- **Pesan Galat**: `"Kurs tidak valid: pembilang dan penyebut wajib bilangan bulat positif."`
- **Penyebab**: Percobaan mendaftarkan atau mengonversi kurs dengan nilai pembilang $\le 0$ atau penyebut $\le 0$ (misal kurs 0/1 atau nilai negatif).
- **Tindakan Perbaikan**: Pastikan nilai kurs valas yang dimasukkan merupakan fraksi integer positif yang sah.

### 2. `EXCHANGE_RATE_NOT_FOUND`
- **Tipe Exception**: `Modules\Treasury\Domain\Exceptions\ExchangeRateNotFoundException`
- **Pesan Galat**: `"Kurs dari {from} ke {to} untuk tanggal {date} dan tipe {type} tidak ditemukan."`
- **Penyebab**: Tidak ada versi kurs yang tersedia di tabel `trs_exchange_rates` untuk pasangan mata uang dan tanggal transaksi yang diminta.
- **Tindakan Perbaikan**: Masukkan kurs harian untuk mata uang tersebut atau pastikan seeder/feed kurs otomatis berjalan.

### 3. `COVENANT_BREACH`
- **Tipe Exception**: `Modules\Treasury\Domain\Exceptions\CovenantBreachException`
- **Pesan Galat**: `"Penarikan fasilitas ditolak: rasio Debt-to-Equity pasca-penarikan ({current_der}‰) melampaui batas covenant ({max_der}‰)."`
- **Penyebab**: Penarikan fasilitas kredit bank akan menyebabkan rasio leverage keuangan grup melanggar kesepakatan kredit perbankan.
- **Tindakan Perbaikan**: Batalkan penarikan, kurangi nominal penarikan, atau ajukan persetujuan *covenant waiver* resmi dari pihak perbankan via otorisasi direksi.

### 4. `CREDIT_LIMIT_EXCEEDED`
- **Tipe Exception**: `InvalidArgumentException`
- **Pesan Galat**: `"Penarikan melampaui plafon kredit yang disetujui."`
- **Penyebab**: Akumulasi penarikan dana ditambah penarikan baru melebihi total batas kredit (`credit_limit_idr`) fasilitas bank.
- **Tindakan Perbaikan**: Ajukan permohonan penambahan plafon kredit ke bank terkait atau gunakan fasilitas bank lain yang masih memiliki sisa limit.

### 5. `POOL_SWEEP_NEGATIVE_BALANCE_FORBIDDEN`
- **Tipe Exception**: `Modules\Treasury\Domain\Exceptions\PoolSweepNegativeBalanceException`
- **Pesan Galat**: `"Penyapuan pooling kas ditolak: saldo rekening anak tidak mencukupi untuk memenuhi target balance tanpa menjadi negatif."`
- **Penyebab**: Nilai sweep yang diminta akan menyebabkan saldo rekening operasional anak berada di bawah nol.
- **Tindakan Perbaikan**: Jalankan kalkulasi zero-balancing dengan batas maksimum saldo aktual atau eksekusi *reverse sweep* untuk menginjeksi likuiditas ke rekening anak.

### 6. `HEDGE_INVALID_TRANSITION`
- **Tipe Exception**: `Modules\Treasury\Domain\Exceptions\HedgeInvalidTransitionException`
- **Pesan Galat**: `"Transisi status kontrak lindung nilai dari {from} ke {to} tidak diizinkan oleh state machine."`
- **Penyebab**: Upaya mengubah status forward contract di luar tabel transisi yang diizinkan (misal dari `draft` langsung ke `settled`).
- **Tindakan Perbaikan**: Ikuti siklus transisi formal state machine 8 status (`draft` $\rightarrow$ `approved` $\rightarrow$ `booked` $\rightarrow$ `active` $\rightarrow$ `matured` $\rightarrow$ `settled`/`rolled`).

### 7. `HEDGE_MAKER_CHECKER_VIOLATION`
- **Tipe Exception**: `Modules\Treasury\Domain\Exceptions\HedgeMakerCheckerViolationException`
- **Pesan Galat**: `"Penyetuju kontrak lindung nilai tidak boleh sama dengan pembuat draf (prinsip Four-Eyes Segregation of Duties)."`
- **Penyebab**: Pengguna yang membuat draft forward contract mencoba menyetujui kontrak itu sendiri.
- **Tindakan Perbaikan**: Minta pejabat atau manajer perbendaharaan lain yang berwenang untuk melakukan peninjauan dan persetujuan.

### 8. `INSUFFICIENT_HISTORY`
- **Tipe Exception**: `InvalidArgumentException`
- **Pesan Galat**: `"Riwayat arus kas tidak mencukupi untuk peramalan musiman (minimum 2 siklus penuh / 8 minggu)."`
- **Penyebab**: Jumlah observasi mingguan historis yang diberikan kurang dari 8 minggu ($2 \times 4$).
- **Tindakan Perbaikan**: Kumpulkan data historis arus kas minimal 8 minggu sebelum menjalankan peramalan musiman.

### 9. `HISTORY_NOT_FULL_CYCLES`
- **Tipe Exception**: `InvalidArgumentException`
- **Pesan Galat**: `"Panjang riwayat arus kas ({n} minggu) wajib merupakan kelipatan penuh dari periode musiman (4 minggu)."`
- **Penyebab**: Jumlah minggu historis bukan kelipatan 4 (misal 10 minggu).
- **Tindakan Perbaikan**: Potong atau lengkapi data arus kas historis hingga tepat kelipatan 4 minggu ($8, 12, 16, \dots$).

### 10. `REVALUATION_ALREADY_REVERSED`
- **Tipe Exception**: `Modules\Treasury\Domain\Exceptions\RevaluationAlreadyReversedException`
- **Pesan Galat**: `"Revaluasi untuk lot valas {lot_id} pada periode {period} telah dibalik sebelumnya."`
- **Penyebab**: Percobaan membalik revaluasi yang status `is_reversed` nya sudah bernilai `true`.
- **Tindakan Perbaikan**: Tidak ada tindakan diperlukan (perilaku idempoten). Sistem mengabaikan proses pembalik berulang.
