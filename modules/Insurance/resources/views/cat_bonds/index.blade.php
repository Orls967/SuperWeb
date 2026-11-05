<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catastrophe Bonds (Cat Bonds)</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 1rem; background: #f8fafc; color: #1e293b; }
        .container { max-width: 1024px; margin: 0 auto; }
        .card { background: #fff; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .btn { display: inline-block; padding: 0.5rem 1rem; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; border: none; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 0.75rem; border-bottom: 1px solid #e2e8f0; font-size: 0.875rem; }
        th { background: #f1f5f9; font-weight: 600; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.25rem; font-size: 0.875rem; font-weight: 500; }
        input { width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Penerbitan Catastrophe Bond (Cat Bond, D157-6)</h1>
        @if(session('status'))
            <div style="background: #dcfce7; color: #166534; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem;">
                {{ session('status') }}
            </div>
        @endif

        <div class="card">
            <h2>Terbitkan Cat Bond Baru (Tokenisasi RWA G7-K3)</h2>
            <form action="{{ route('insurance.cat-bonds.store') }}" method="POST">
                @csrf
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; min-width: 140px;">
                        <label>Kode Bond</label>
                        <input type="text" name="bond_code" value="CAT-BOND-2026-A" required>
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 140px;">
                        <label>Simbol Token RWA</label>
                        <input type="text" name="asset_code" value="RWA-CAT-26A" required>
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 160px;">
                        <label>Pokok Obligasi (IDR)</label>
                        <input type="number" name="principal_idr" value="10000000000" required>
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 100px;">
                        <label>Total Unit</label>
                        <input type="number" name="units" value="1000" required>
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 120px;">
                        <label>Spread Kupon (bps)</label>
                        <input type="number" name="spread_bps" value="600" required>
                    </div>
                </div>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; min-width: 160px;">
                        <label>Attachment Trigger (IDR)</label>
                        <input type="number" name="attachment_idr" value="2000000000" required>
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 160px;">
                        <label>Exhaustion Trigger (IDR)</label>
                        <input type="number" name="exhaustion_idr" value="4000000000" required>
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 140px;">
                        <label>Tanggal Terbit</label>
                        <input type="date" name="issue_date" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 140px;">
                        <label>Tanggal Jatuh Tempo</label>
                        <input type="date" name="maturity_date" value="{{ date('Y-m-d', strtotime('+3 years')) }}" required>
                    </div>
                </div>
                <button type="submit" class="btn">Terbitkan Cat Bond</button>
            </form>
        </div>

        <div class="card">
            <h2>Daftar Cat Bond Terbit</h2>
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Token RWA</th>
                        <th>Pokok Awal</th>
                        <th>Outstanding</th>
                        <th>Unit</th>
                        <th>Spread</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bonds as $b)
                        <tr>
                            <td><strong>{{ $b->bond_code }}</strong></td>
                            <td>{{ $b->asset_code }}</td>
                            <td>Rp {{ number_format($b->principal_idr, 0, ',', '.') }}</td>
                            <td><strong>Rp {{ number_format($b->outstanding_principal_idr, 0, ',', '.') }}</strong></td>
                            <td>{{ $b->units }}</td>
                            <td>{{ $b->spread_bps }} bps</td>
                            <td><span style="font-weight: 600;">{{ strtoupper($b->status->value) }}</span></td>
                            <td><a href="{{ route('insurance.cat-bonds.show', $b->id) }}" class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8">Belum ada Cat Bond terdaftar.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div style="margin-top: 1rem;">{{ $bonds->links() }}</div>
        </div>
    </div>
</body>
</html>

