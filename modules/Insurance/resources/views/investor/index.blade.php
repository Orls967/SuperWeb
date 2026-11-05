<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Investor Cat Bond</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 1rem; background: #f8fafc; color: #1e293b; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: #fff; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .btn { display: inline-block; padding: 0.5rem 1rem; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; border: none; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 0.75rem; border-bottom: 1px solid #e2e8f0; font-size: 0.875rem; }
        th { background: #f1f5f9; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Portal Investor Catastrophe Bond (D157-6)</h1>
        <p style="color: #64748b;">Informasi transparan posisi investasi, imbal hasil kupon, dan status trigger bencana tanpa data pribadi nasabah (Anti-PII).</p>

        <div class="card">
            <h2>Daftar Cat Bond Tersedia</h2>
            <table>
                <thead>
                    <tr>
                        <th>Kode Bond</th>
                        <th>Simbol Token RWA</th>
                        <th>Outstanding Pokok</th>
                        <th>Total Unit</th>
                        <th>Kupon p.a.</th>
                        <th>Jatuh Tempo</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bonds as $b)
                        <tr>
                            <td><strong>{{ $b->bond_code }}</strong></td>
                            <td>{{ $b->asset_code }}</td>
                            <td>Rp {{ number_format($b->outstanding_principal_idr, 0, ',', '.') }}</td>
                            <td>{{ number_format($b->units) }}</td>
                            <td>{{ $b->spread_bps / 100 }}%</td>
                            <td>{{ $b->maturity_date?->format('Y-m-d') }}</td>
                            <td><a href="{{ route('investor.cat-bonds.show', $b->id) }}" class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Lihat Portofolio Saya</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Belum ada Cat Bond diterbitkan.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div style="margin-top: 1rem;">{{ $bonds->links() }}</div>
        </div>
    </div>
</body>
</html>

