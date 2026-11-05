<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portofolio Cat Bond {{ $bond->bond_code }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 1rem; background: #f8fafc; color: #1e293b; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: #fff; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .btn { display: inline-block; padding: 0.5rem 1rem; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; border: none; cursor: pointer; }
        .btn-outline { background: #fff; color: #475569; border: 1px solid #cbd5e1; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-top: 1rem; }
        table { width: 100%; border-collapse: collapse; text-align: left; margin-top: 0.5rem; }
        th, td { padding: 0.5rem; border-bottom: 1px solid #e2e8f0; font-size: 0.875rem; }
        th { background: #f8fafc; }
    </style>
</head>
<body>
    <div class="container">
        <a href="{{ route('investor.cat-bonds.index') }}" class="btn btn-outline" style="margin-bottom: 1rem;">&larr; Kembali</a>

        <div class="card">
            <h1>Kepemilikan Unit Cat Bond {{ $bond->bond_code }}</h1>
            <div class="grid">
                <div><strong>Token Aset:</strong> {{ $bond->asset_code }}</div>
                <div><strong>Unit Dimiliki:</strong> {{ number_format($units) }} unit</div>
                <div><strong>Nilai Portofolio:</strong> Rp {{ number_format($currentValueIdr, 0, ',', '.') }}</div>
                <div><strong>Kupon Spread:</strong> {{ $bond->spread_bps / 100 }}% p.a.</div>
                <div><strong>Status Obligasi:</strong> {{ strtoupper($bond->status->value) }}</div>
                <div><strong>Jatuh Tempo:</strong> {{ $bond->maturity_date?->format('Y-m-d') }}</div>
            </div>
        </div>

        <div class="card">
            <h3>Riwayat Penerimaan Kupon Saya</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID Payout</th>
                        <th>Kupon ID</th>
                        <th>Unit Terdaftar</th>
                        <th>Jumlah Kupon Diterima (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($coupons as $cp)
                        <tr>
                            <td>#{{ $cp->id }}</td>
                            <td>#{{ $cp->coupon_id }}</td>
                            <td>{{ number_format($cp->units) }}</td>
                            <td><strong>Rp {{ number_format($cp->amount_idr, 0, ',', '.') }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Belum ada kupon diterima untuk akun ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

