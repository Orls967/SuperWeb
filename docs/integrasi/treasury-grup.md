# Panduan Integrasi Lintas Modul: Treasury Korporat

Dokumen ini mendokumentasikan spesifikasi integrasi teknis domain **Treasury** dengan modul-modul lain di ekosistem platform: **Banking (Buku Besar/Ledger)**, **Core (EventSpine & TaskInbox)**, **Intercompany**, dan **Enterprise Finance**.

---

## 1. Integrasi Buku Besar (Modules\Banking\Application\Services\LedgerService)

Modul Treasury beroperasi di atas buku besar perbankan universal yang menerapkan aturan *single-sign convention*. Seluruh mutasi moneter diposting secara atomik melalui DTO `PostingDTO`.

### Aturan Single-Sign Convention:
- **Debit**: Bertanda negatif (`-amount`). Contoh: Penambahan aset kas bank atau pengakuan beban selisih kurs.
- **Kredit**: Bertanda positif (`+amount`). Contoh: Pengurangan aset kas bank, penambahan kewajiban fasilitas kredit, atau pengakuan pendapatan selisih kurs.
- **Netto Nol**: $\sum \text{lines} = 0$ per kode mata uang.
- **Akun Aset Bank Operasional**: Dikonfigurasi dengan opsi `'allow_negative' => true` pada metadata rekening perbendaharaan karena saldo normal debit disimpan sebagai nilai negatif.

### Pemetaan Akun Ledger Utama (`TreasuryAccounts`):
- `trs:bank_operational:{ENTITY}:{CURRENCY}`: Rekening giro/kas operasional bank.
- `trs:clearing_intercompany:{ENTITY}:{CURRENCY}`: Rekening perantara transfer kliring antar-rekening/antar-entitas.
- `trs:fx_unrealized_gain:{CURRENCY}`: Akun pendapatan laba kurs belum terealisasi (kredit).
- `trs:fx_unrealized_loss:{CURRENCY}`: Akun beban rugi kurs belum terealisasi (debit).
- `trs:fx_realized_gain:{CURRENCY}`: Akun pendapatan laba kurs terealisasi settlement.
- `trs:fx_realized_loss:{CURRENCY}`: Akun beban rugi kurs terealisasi settlement.
- `trs:credit_facility_payable:{BANK}:{FACILITY}`: Kewajiban pokok pinjaman kredit bank (kredit).
- `trs:pool_sweep_clearing:{POOL_CODE}:{CURRENCY}`: Rekening kliring penyapuan notional pooling.
- `trs:petty_cash_imprest:{ENTITY}:{CURRENCY}`: Rekening dana tetap kas kecil (*imprest fund*).

---

## 2. Integrasi Bus Event (Modules\Core\Contracts\EventSpineInterface)

Seluruh peristiwa signifikan dalam siklus hidup perbendaharaan dipublikasikan ke bus event spine terpusat melalui service `TreasuryEventPublisher`.

### Topik Event:
`treasury.events`

### Katalog Event & Skema:
1. **`treasury.fx_revaluation.completed.v1`**:
   - Diterbitkan saat perhitungan revaluasi akhir bulan PSAK 10 berhasil diposting.
   - Payload: `revaluation_id`, `period`, `currency`, `foreign_balance_minor`, `book_functional_idr`, `revalued_functional_idr`, `gain_loss_idr`, `certificate_hash`.
2. **`treasury.hedge_contract.transitioned.v1`**:
   - Diterbitkan saat status kontrak forward berpindah siklus hidup (misal: designated, active, matured, settled).
   - Payload: `contract_id`, `contract_number`, `from_status`, `to_status`, `actor_id`, `reason`.
3. **`treasury.facility.drawdown_executed.v1`**:
   - Diterbitkan saat penarikan fasilitas pinjaman bank dieksekusi.
   - Payload: `facility_id`, `facility_code`, `bank_name`, `drawdown_amount_minor`, `currency`, `new_total_drawn_minor`, `current_der_permille`, `max_der_permille`.
4. **`treasury.cash_pool.sweep_executed.v1`**:
   - Diterbitkan saat penyapuan kas pool zero-balancing dieksekusi.
   - Payload: `pool_id`, `pool_code`, `source_account_id`, `target_account_id`, `sweep_amount_minor`, `currency`, `posting_reference`.
5. **`treasury.bank_reconciliation.completed.v1`**:
   - Diterbitkan saat sesi pemadanan rekening koran selesai.
   - Payload: `bank_account_id`, `account_number`, `currency`, `matched_count`, `unmatched_count`, `certificate_hash`.
6. **`treasury.petty_cash.voucher_processed.v1`**:
   - Diterbitkan saat pencairan atau penggantian kas kecil dicatat.
   - Payload: `voucher_id`, `voucher_number`, `action_type`, `amount_minor`, `remaining_balance_minor`.

### Kepatuhan Evolusi Skema Aditif (Algoritma A6):
Skema seluruh event didaftarkan pada `EventSchemaRegistry` dengan penegakan kompatibilitas ke belakang: penambahan atribut bersifat opsional (*nullable* atau memiliki nilai default), tanpa perubahan tipe data atau penghapusan properti yang sudah terdaftar.

---

## 3. Integrasi Penugasan Operator (Modules\Core\Application\Actions\TaskInbox\TaskInboxService)

Untuk mencegah risiko kepatuhan dan kebocoran dana, modul Treasury mengotomatiskan penerbitan tugas operasional (*CoreTask*) via `TreasuryTaskInboxNotifier`:

### Skenario Penerbitan Tugas:
1. **Peringatan Batas Rasio Covenant DER**:
   - Memicu: Rasio DER saat penarikan mencapai $\ge 90\%$ dari plafon batas pinjaman.
   - Peran Ditugaskan: `treasury`
   - Prioritas: `urgent` jika melanggar, `high` jika mendekati batas.
2. **Pos Mutasi Rekening Koran Tak Terpadankan**:
   - Memicu: Terdapat mutasi bank impor yang tidak cocok pada seluruh 3 tahap rekonsiliasi.
   - Peran Ditugaskan: `treasury`
   - Prioritas: `medium`
3. **Pemberitahuan Jatuh Tempo Kontrak Lindung Nilai (Hedge)**:
   - Memicu: Kontrak forward aktif yang memiliki sisa masa berlaku $\le 7$ hari.
   - Peran Ditugaskan: `treasury`
   - Prioritas: `high`
4. **Peringatan Saldo Kas Kecil Menipis**:
   - Memicu: Saldo kas kecil berada di bawah $20\%$ dari pagu dana tetap (*imprest limit*).
   - Peran Ditugaskan: `treasury`
   - Prioritas: `low` / `medium`

---

## 4. Integrasi Intercompany (Fase 52) & Enterprise Finance (Fase 54)

- **Intercompany Clearing**: Rekening `trs:pool_sweep_clearing` memetakan langsung ke subledger pinjaman antar-entitas (`ic_loans`) di Fase 52.
- **Konsolidasi Pelaporan Finansial**: Laporan arus kas 13 minggu (`CashForecast13WeekReportExporter`) diintegrasikan langsung sebagai input peramalan likuiditas korporat di modul Enterprise Finance (Fase 54).
- **Pengawasan Tata Kelola Utang**: Covenant fasilitas kredit dipantau secara terpusat oleh service `ConglomerateCapitalAndGovernanceService` pada Fase 54.

---

## 5. Protokol Sinkronisasi dan Idempotensi Antar-Sistem

Setiap pemanggilan API atau eksekusi batch antar-modul wajib menerapkan aturan:
1. **Idempotency Key Format**: `IDEM:{SENDER_MODULE}:{ACTION}:{ENTITY_TYPE}:{ENTITY_ID}:{TIMESTAMP_OR_HASH}`.
2. **Two-Phase Commit Simulation**:
   - Langkah 1: Validasi prasyarat (kecukupan limit fasilitas kredit, ketersediaan saldo valas).
   - Langkah 2: Posting jurnal buku besar perbankan secara atomik dalam transaksi basis data tunggal.
   - Langkah 3: Jika berhasil, terbitkan domain event ke bus EventSpine dan perbarui subledger lot valas.
3. **Kompensasi Transaksi (Saga Pattern)**: Bila terjadi kegagalan jaringan atau pembatalan di modul penerima, modul Treasury memposting jurnal pembalik persis dengan tanda single-sign berlawanan tanpa pernah memodifikasi catatan audit historis.
