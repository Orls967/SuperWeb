<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cat Bond {{ $bond->bond_code }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 1rem; background: #f8fafc; color: #1e293b; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: #fff; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .btn { display: inline-block; padding: 0.5rem 1rem; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; border: none; cursor: pointer; }
        .btn-outline { background: #fff; color: #475569; border: 1px solid #cbd5e1; }
        table { width: 100%; border-collapse: collapse; text-align: left; margin-top: 0.5rem; }
        th, td { padding: 0.5rem; border-bottom: 1px solid #e2e8f0; font-size: 0.875rem; }
        th { background: #f8fafc; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <a href="{{ route('insurance.cat-bonds.index') }}" class="btn btn-outline" style="margin-bottom: 1rem;">&larr; Kembali</a>

        <div class="card">
            <h1>Cat Bond {{ $bond->bond_code }}</h1>
            <div class="grid">
                <div><strong>Token RWA:</strong> {{ $bond->asset_code }} (ID: {{ $bond->rwa_asset_id }})</div>
                <div><strong>Pokok Awal:</strong> Rp {{ number_format($bond->principal_idr, 0, ',', '.') }}</div>
                <div><strong>Pokok Tersisa:</strong> Rp {{ number_format($bond->outstanding_principal_idr, 0, ',', '.') }}</div>
                <div><strong>Total Unit:</strong> {{ number_format($bond->units) }} unit</div>
                <div><strong>Spread Kupon:</strong> {{ $bond->spread_bps }} bps p.a.</div>
                <div><strong>Attachment:</strong> Rp {{ number_format($bond->attachment_idr, 0, ',', '.') }}</div>
                <div><strong>Exhaustion:</strong> Rp {{ number_format($bond->exhaustion_idr, 0, ',', '.') }}</div>
                <div><strong>Status:</strong> {{ strtoupper($bond->status->value) }}</div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem; flex-wrap: wrap;">
                <!-- Bayar Kupon Kuartalan -->
                <div style="flex: 1; min-width: 250px; background: #f8fafc; padding: 1rem; border-radius: 6px;">
                    <h3>Distribusi Kupon Kuartalan</h3>
                    <form action="{{ route('insurance.cat-bonds.coupon', $bond->id) }}" method="POST">
                        @csrf
                        <label style="display: block; font-size: 0.875rem; margin-bottom: 0.25rem;">Kuartal (YYYY-Q1..4)</label>
                        <input type="text" name="quarter_key" value="{{ date('Y') }}-Q1" required style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; margin-bottom: 0.5rem;">
                        <button type="submit" class="btn" style="background: #059669; width: 100%;">Bayar Kupon ke Holder RWA</button>
                    </form>
                </div>

                <!-- Aplikasi Trigger Indemnity -->
                <div style="flex: 1; min-width: 250px; background: #fef2f2; padding: 1rem; border-radius: 6px;">
                    <h3 style="color: #991b1b;">Aplikasi Trigger Indemnity</h3>
                    <form action="{{ route('insurance.cat-bonds.trigger', $bond->id) }}" method="POST">
                        @csrf
                        <label style="display: block; font-size: 0.875rem; margin-bottom: 0.25rem;">Kerugian Industri (IDR)</label>
                        <input type="number" name="industry_loss_idr" value="3000000000" required style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; margin-bottom: 0.5rem;">
                        <input type="hidden" name="event_key" value="CAT-EV-{{ date('YmdHis') }}">
                        <button type="submit" class="btn" style="background: #dc2626; width: 100%;">Eksekusi Reduksi Pokok</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>Riwayat Pembayaran Kupon</h3>
            <table>
                <thead>
                    <tr>
                        <th>Kuartal</th>
                        <th>Outstanding Basis</th>
                        <th>Total Kupon Dibayar</th>
                        <th>Waktu Bayar</th>
                        <th>Ledger Tx</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bond->coupons as $c)
                        <tr>
                            <td><strong>{{ $c->quarter_key }}</strong></td>
                            <td>Rp {{ number_format($c->outstanding_idr, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($c->total_idr, 0, ',', '.') }}</td>
                            <td>{{ $c->paid_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            <td>{{ $c->ledger_transaction_id }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Belum ada kupon dibagikan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($bond->triggerEvents->isNotEmpty())
            <div class="card">
                <h3>Kejadian Trigger Bencana & Write-Down</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Event Key</th>
                            <th>Kerugian Industri</th>
                            <th>Reduksi Pokok</th>
                            <th>Sisa Pokok</th>
                            <th>Waktu Eksekusi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bond->triggerEvents as $ev)
                            <tr>
                                <td><strong>{{ $ev->event_key }}</strong></td>
                                <td>Rp {{ number_format($ev->industry_loss_idr, 0, ',', '.') }}</td>
                                <td><span style="color: #dc2626;">- Rp {{ number_format($ev->reduction_idr, 0, ',', '.') }}</span></td>
                                <td>Rp {{ number_format($ev->outstanding_after_idr, 0, ',', '.') }}</td>
                                <td>{{ $ev->occurred_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</body>
</html>

