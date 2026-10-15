<?php

declare(strict_types=1);

use App\Quality\Progress\ProgressIntegrityScanner;
use Tests\TestCase;

/*
| PROGRESS P7 / VERIFIKATOR PUTARAN 2: Test Pembukti Sabotase S9.
| Membuktikan bahwa minus deliverable fase yang sudah tercentang [x] (seperti M-R0-21)
| DILARANG dialihkan (wajib ditolak oleh ProgressIntegrityScanner) meskipun ID minus tersebut
| tercantum dalam teks docs/DECISIONS.md (misalnya pada bagian larangan pengalihan deliverable).
*/

uses(TestCase::class);

it('rejects redirection of deliverable minuses even when mentioned in DECISIONS.md (Sabotase S9)', function (): void {
    $progressPath = base_path('docs/PROGRESS.md');
    $root = base_path();

    $content = (string) file_get_contents($progressPath);

    // Lakukan sabotase S9 pada dokumen PROGRESS.md:
    // Beri M-R0-21 (deliverable R0.12 yang tercentang [x]) pengalihan ke R5.5
    $targetLine = '| ~~M-R0-21~~ | R0.4 / R0.12 | ~~Skor mutasi `App\Quality\Progress\ProgressIntegrityScanner` 59.24% (327 killed dari 552 mutasi) berada di bawah ambang batas pagar ≥ 60.0%~~ | ~~Integritas dokumen dan bukti berpotensi lolos dari deteksi bila pagar mutasi scanner di bawah ambang batas~~ | ~~P1~~ | Tuntas di T5 (skor mutasi naik ke 75.16%, 462 tested dari 616 mutasi; commit `ac1cb02`) |';
    $tamperedLine = '| M-R0-21 | R0.12 | Skor mutasi `App\Quality\Progress\ProgressIntegrityScanner` 59.24% (< 60.0% ambang batas) | Ketahanan pagar integritas dokumen dan bukti belum teruji mutasi secara optimal | P1 | ↪️ dialihkan ke R5.5 (DECISIONS.md#2026-10-14-p9-minus-dialihkan) |';

    expect(str_contains($content, $targetLine))->toBeTrue('Baris M-R0-21 tidak ditemukan di docs/PROGRESS.md');

    $tamperedContent = str_replace($targetLine, $tamperedLine, $content);

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($tamperedContent);
    $violations = $scanner->validate($phases, repoRoot: $root);

    // Scanner WAJIB menolak pengalihan deliverable M-R0-21
    $expectedViolation = "Minus M-R0-21: item 'R0.12' merupakan deliverable fase R0 yang sudah tercentang [x], pengalihan ditolak.";
    expect($violations)->toContain($expectedViolation);
});
