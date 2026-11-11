# Panduan Pengguna: Budget Owner — Perencanaan & Eksekusi Anggaran Departemen (Fase 54)

## 1. Peran & Tanggung Jawab
Budget Owner adalah pimpinan unit kerja atau kepala departemen yang memiliki wewenang untuk:
1. Menyusun usulan rencana anggaran tahunan departemen (DRAFT RKAP).
2. Memonitor sisa pagu bebas sebelum mengajukan permintaan pengadaan (Purchase Requisition).
3. Mengajukan revisi alokasi pagu saat terjadi perubahan prioritas program kerja.
4. Memastikan realisasi belanja operasional unit kerja tidak melampaui batas plafon pagu (Hard-Stop).

---

## 2. Siklus Hidup Anggaran (8 Status State Machine)

Penyusunan anggaran tahunan wajib melalui 8 status baku sesuai prinsip Maker-Checker:
```
DRAFT -> SUBMITTED -> REVIEWED -> APPROVED -> ACTIVE -> REVISED -> FROZEN -> CLOSED
```

### Aturan Ketat Maker-Checker:
- Pengguna yang membuat draf anggaran (`created_by`) dilarang keras menyetujui anggarannya sendiri (`approved_by`).
- Pelanggaran akan digagalkan oleh sistem dengan pengecualian `BudgetMakerCheckerViolationException`.
- Persetujuan akhir wajib dilakukan oleh Finance Controller atau Direktur Keuangan.

---

## 3. Prosedur Reservasi Komitmen Belanja (Encumbrance)

Saat unit kerja hendak melakukan pembelian alat atau jasa via modul Procurement:
1. Masuk ke modul Procurement dan ajukan Purchase Requisition (PR) dengan memilih Kode Anggaran terkait.
2. Modul Procurement memanggil kontrak `BudgetCommitmentPort::reserve()`.
3. Jika pagu mencukupi:
   - Dana belanja langsung diblokir dan status komitmen menjadi `RESERVED`.
   - PO dapat diterbitkan ke vendor.
4. Jika pagu tidak mencukupi (Hard-Stop):
   - Transaksi ditolak seketika.
   - Budget Owner harus mengajukan revisi penambahan pagu atau realokasi dari mata anggaran lain sebelum PO dapat diproses.
5. Saat barang/jasa tiba dan faktur disetujui:
   - Modul Keuangan memanggil `BudgetCommitmentPort::liquidate()`.
   - Dana berpindah dari Komitmen Tertahan menjadi Realisasi Belanja (Spent).

