# ADR-0048: Gerbang Kelaikan Terbang dan Penguncian Dispatch (G7-D48)

## Status
Diterima (Accepted) — 2026-11-08

## Konteks
Kelaikan terbang (*airworthiness*) merupakan regulasi keselamatan penerbangan wajib yang diatur oleh otoritas penerbangan sipil (CASR/DGCA). Pesawat dengan sertifikat kalender kedaluwarsa, jam terbang melebihi batas A-Check/C-Check, atau item Minimum Equipment List (MEL) yang masa penundaannya (*deferral*) telah lewat tidak boleh diberangkatkan.

## Keputusan
1. **Gerbang Wajib Pra-Dispatch:** Penerbangan hanya dapat berpindah ke status `airworthy_cleared` bila algoritma A2 `AirworthinessDispatchGate` menyatakan izin `PASSED` pada saat verifikasi dijalankan.
2. **Pemblokiran & Work Order Otomatis:** Kegagalan verifikasi A2 langsung mengunci pesawat ke status `grounded`, memblokir dispatch penerbangan, dan secara otomatis memicu Work Order (WO) perbaikan melalui kontrak modul Asset `MaintenanceWorkOrderRequester` (G7-K9).
3. **Persetujuan Deferral MEL:** Penundaan perbaikan MEL hanya sah apabila disetujui melalui `Core\ApprovalEngineInterface` dengan melampirkan tanggal kedaluwarsa deferral definitif dan justifikasi teknis inspektur berwenang.

## Konsekuensi
Menjamin tidak ada penerbangan tanpa sertifikasi kelaikan aktif yang lolos ke proses boarding atau keberangkatan.

