<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Sertifikat Digital — Google Ecosystem</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #1e293b; padding: 1rem; display: flex; justify-content: center; }
        .card { width: 100%; max-width: 480px; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); padding: 1.5rem; margin-top: 1rem; }
        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem; margin-bottom: 1rem; }
        .badge { font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.75rem; border-radius: 9999px; text-transform: uppercase; }
        .badge-active { background: #dcfce7; color: #15803d; }
        .badge-expired { background: #fef3c7; color: #b45309; }
        .badge-revoked { background: #fee2e2; color: #b91c1c; }
        .prop-group { margin-bottom: 0.75rem; }
        .prop-label { font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 0.25rem; }
        .prop-value { font-size: 0.95rem; font-weight: 500; word-break: break-all; }
        .hash-code { font-family: monospace; font-size: 0.8rem; background: #f1f5f9; padding: 0.35rem 0.5rem; border-radius: 4px; word-break: break-all; }
        .seal { text-align: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px dashed #cbd5e1; font-size: 0.75rem; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div>
                <h1 style="font-size: 1.15rem; font-weight: 700;">Verifikasi Sertifikat</h1>
                <p style="font-size: 0.8rem; color: #64748b;">Akreditasi Kompetensi Digital</p>
            </div>
            @if($status === 'ACTIVE')
                <span class="badge badge-active">Resmi & Aktif</span>
            @elseif($status === 'EXPIRED')
                <span class="badge badge-expired">Kedaluwarsa</span>
            @else
                <span class="badge badge-revoked">Dicabut</span>
            @endif
        </div>

        <div class="prop-group">
            <div class="prop-label">Nomor Sertifikat</div>
            <div class="prop-value" style="color: #2563eb; font-weight: 700;">{{ $cert->certificate_number }}</div>
        </div>

        <div class="prop-group">
            <div class="prop-label">Program Sertifikasi</div>
            <div class="prop-value">{{ $cert->program ? $cert->program->title : $cert->skill_competency_code }}</div>
        </div>

        <div class="prop-group">
            <div class="prop-label">Kode Program</div>
            <div class="prop-value">{{ $cert->program ? $cert->program->program_code : $cert->skill_competency_code }}</div>
        </div>

        <div style="display: flex; gap: 1rem;">
            <div class="prop-group" style="flex: 1;">
                <div class="prop-label">Tanggal Terbit</div>
                <div class="prop-value">{{ $cert->issued_at ? \Carbon\Carbon::parse($cert->issued_at)->format('d M Y') : '-' }}</div>
            </div>
            <div class="prop-group" style="flex: 1;">
                <div class="prop-label">Berlaku Sampai</div>
                <div class="prop-value">{{ $cert->expires_at ? \Carbon\Carbon::parse($cert->expires_at)->format('d M Y') : '-' }}</div>
            </div>
        </div>

        <div class="prop-group">
            <div class="prop-label">Hash Kanonik Rantai Integritas (SHA-256)</div>
            <div class="hash-code">{{ $cert->certificate_hash }}</div>
        </div>

        <div class="seal">
            <p>Terverifikasi melalui Jaringan Kriptografis Ekosistem Terpadu.</p>
            <p style="margin-top: 0.25rem;">Tidak membocorkan data pribadi siswa (PII-protected).</p>
        </div>
    </div>
</body>
</html>
