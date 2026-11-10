# ADR-0067: Akuntansi Lindung Nilai Kontrak Forward & Evaluasi Mark-to-Market Harian

## Status
DITERIMA (2026-11-11) — Keputusan W8-D67 (Gelombang 8, Prompt P5).

## Konteks
Komitmen pembelian impor atau pelunasan pinjaman valas di masa depan menghadapkan perusahaan pada risiko kenaikan kurs. Kontrak forward derivatif digunakan untuk mengunci kurs masa depan. Pengelolaan instrumen ini membutuhkan siklus hidup formal state machine yang diawasi otorisasi dual-control serta pencatatan nilai wajar Mark-to-Market (MTM) harian sesuai PSAK 71.

## Keputusan
1. **State Machine HedgeContract (8 Status)**:
   `draft` $\rightarrow$ `approved` $\rightarrow$ `booked` $\rightarrow$ `active` $\rightarrow$ `matured` $\rightarrow$ `settled` / `rolled` / `cancelled`.
2. **Prinsip Four-Eyes (Maker-Checker)**: Transisi dari `draft` ke `approved` dikelola oleh `ApprovalEngine` dengan guard mutlak pembuat tidak boleh sama dengan penyetuju (`creator_id != approver_id`).
3. **Pencatatan Mark-to-Market (MTM)**: Nilai wajar dihitung secara rasional $\text{Nosional} \times (\text{Kurs Pasar} - \text{Kurs Kontrak})$ dan diposting harian ke akun neraca aset/liabilitas derivatif (`trs:derivative_asset:IDR` / `trs:derivative_liability:IDR`) terhadap `trs:derivative_gain_loss:IDR`.
4. **Penyelesaian & Roll-Over**: Kontrak jatuh tempo diselesaikan secara kas (*settled*) atau diperpanjang (*rolled*) dengan membuat kontrak forward anak yang terhubung relasional (`rolled_to_contract_id`), menutup posisi MTM kontrak induk.
5. **Biaya Pembatalan**: Pembatalan kontrak yang telah dibukukan (`booked` $\rightarrow$ `cancelled`) mewajibkan pembukuan biaya denda pembatalan bank (`trs:hedge_cancellation_cost:IDR`).

## Konsekuensi
- Menjamin kepatuhan terhadap standar akuntansi derivatif PSAK 71 dan mencegah manipulasi otorisasi instrumen keuangan berisiko tinggi.
