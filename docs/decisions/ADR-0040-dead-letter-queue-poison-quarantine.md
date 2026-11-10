# ADR-0040: Dead-Letter Queue & Poison Message Quarantine (W8-D40)

## Status
Diterima (Accepted)

## Konteks
Dalam arsitektur streaming atau event spine berbasis offset berurutan, satu pesan yang rusak, korup, atau gagal diproses (*poison message*) dapat memblokir seluruh antrean pesan berikutnya (*head-of-line blocking*) bagi konsumen terkait. Di sisi lain, membuang pesan gagal begitu saja tanpa pengawasan berpotensi menghilangkan data penting (*silent data loss*). Dibutuhkan arsitektur Dead-Letter Queue (DLQ) dan mekanisme karantina poison message yang aman, transparan, dan terisolasi.

## Keputusan
1. **Dead-Letter Queue Terpadu (DLQ Console & Service)**:
   Pesan yang telah melampaui batas maksimum percobaan (default 6 kali percobaan) atau mengalami error fatal diklasifikasikan ke status `dead_lettered` pada tabel `core_event_deliveries`.
   Service `DeadLetterQueueService` menyediakan:
   - Pencarian dan filter pesan DLQ berdasarkan kriteria: `consumer_group`, `topic`, `failure_reason`, `occurred_at_range`.
   - Replay batch dua langkah (`spine:dlq-replay`).
   - Discard pesan ber-alasan (`spine:dlq-discard`).

2. **Karantina Poison Message Non-Blocking (`core_poison_messages`)**:
   Pesan yang mengalami kegagalan validasi skema berturut-turut atau memicu exception berulang sebanyak 3 kali berturut-turut tanpa perbaikan segera dipindahkan ke tabel karantina `core_poison_messages`.
   - Offset konsumen dapat terus maju melompati poison message tersebut sehingga alur pesan yang sehat tetap lancar (*non-blocking stream progression*).
   - Poison message yang dikarantina mencatat payload asli, stack trace exception, skema pelanggaran, dan waktu kejadian untuk investigasi forensik teknis.

3. **Tata Kelola Penghapusan / Discard & Bridge Approval Engine**:
   - Pembuangan pesan DLQ tunggal memerlukan alasan bisnis tertulis yang tercatat permanen dalam audit trail.
   - Pembuangan pesan DLQ secara massal (*batch discard* $> 20$ pesan) **wajib melalui persetujuan berjenjang** melalui integrasi `ApprovalEngine` dan menerbitkan tugas persetujuan di Kotak Masuk "Tugas Saya" (`TaskInboxService::createTask()`) untuk peran `spine_operator` dan supervisor.

## Konsekuensi
- Mencegah kemacetan total antrean event akibat pesan rusak.
- Menjamin setiap penghapusan pesan memiliki jejak audit dan persetujuan operasional resmi.

