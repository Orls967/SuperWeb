<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Treaty {{ $treaty->treaty_code }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 1rem; background: #f8fafc; color: #1e293b; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: #fff; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .btn { display: inline-block; padding: 0.5rem 1rem; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; border: none; cursor: pointer; }
        .btn-outline { background: #fff; color: #475569; border: 1px solid #cbd5e1; }
        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-draft { background: #fef3c7; color: #92400e; }
        .badge-bound { background: #e0e7ff; color: #3730a3; }
        .badge-commuted { background: #f1f5f9; color: #475569; }
        table { width: 100%; border-collapse: collapse; text-align: left; margin-top: 0.5rem; }
        th, td { padding: 0.5rem; border-bottom: 1px solid #e2e8f0; font-size: 0.875rem; }
        th { background: #f8fafc; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <a href="{{ route('insurance.reinsurance.treaties.index') }}" class="btn btn-outline" style="margin-bottom: 1rem;">&larr; Kembali ke Daftar</a>
        
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                    <h1 style="margin: 0;">{{ $treaty->treaty_code }} - {{ $treaty->name }}</h1>
                    <p style="margin: 0.25rem 0 0 0; color: #64748b;">Tipe: {{ $treaty->type->label() }} | Lini: {{ $treaty->line_category }}</p>
                </div>
                <span class="badge badge-{{ $treaty->status->value }}">{{ $treaty->status->label() }}</span>
            </div>

            <div class="grid">
                <div><strong>Inception:</strong> {{ $treaty->inception_date->format('Y-m-d') }}</div>
                <div><strong>Expiry:</strong> {{ $treaty->expiry_date->format('Y-m-d') }}</div>
                <div><strong>Quota Share:</strong> {{ $treaty->quota_share_bps }} bps</div>
                <div><strong>Komisi:</strong> {{ $treaty->commission_bps }} bps</div>
                <div><strong>Limit / Attachment:</strong> Rp {{ number_format($treaty->limit_idr, 0, ',', '.') }} / Rp {{ number_format($treaty->attachment_idr, 0, ',', '.') }}</div>
            </div>

            <div style="margin-top: 1.5rem;">
                <h3>Panel Reinsurer</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Party ID</th>
                            <th>Nama Reinsurer</th>
                            <th>Share (bps)</th>
                            <th>Share (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($treaty->panel as $item)
                            <tr>
                                <td>{{ $item->reinsurer_party_id }}</td>
                                <td>{{ $item->reinsurer?->legal_name ?? $item->reinsurer?->name ?? 'Party #'.$item->reinsurer_party_id }}</td>
                                <td>{{ $item->share_bps }}</td>
                                <td>{{ $item->share_bps / 100 }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($treaty->status->transitions() !== [])
                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                    <h3>Transisi Status</h3>
                    <form action="{{ route('insurance.reinsurance.treaties.transition', $treaty->id) }}" method="POST" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        @csrf
                        <select name="target_status" required style="padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                            @foreach($treaty->status->transitions() as $target)
                                <option value="{{ $target->value }}">{{ $target->label() }} {{ $treaty->status->requiresApprovalTo($target) ? '(Perlu Approval)' : '' }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="reason" placeholder="Alasan transisi..." style="padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; flex: 1;">
                        <button type="submit" class="btn">Terapkan Transisi</button>
                    </form>
                </div>
            @endif
        </div>

        <div class="card">
            <h3>Riwayat Transisi (Append-Only)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Dari</th>
                        <th>Menuju</th>
                        <th>Alasan</th>
                        <th>Actor</th>
                        <th>Approval Ref</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($treaty->history as $h)
                        <tr>
                            <td>{{ $h->occurred_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            <td>{{ $h->from_status ?? 'Draf Awal' }}</td>
                            <td><strong>{{ $h->to_status }}</strong></td>
                            <td>{{ $h->reason }}</td>
                            <td>{{ $h->actor_user_id }}</td>
                            <td>{{ $h->approval_ref ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Belum ada riwayat transisi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

