<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Mutu & Ketertelusuran (QMS)</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-red-800">
                    <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="flex items-center justify-between rounded-lg bg-indigo-50 px-4 py-3">
                <p class="text-sm text-indigo-800">39.1–39.8 — rencana inspeksi, SPC, NCR/CAPA, recall, sertifikat, kalibrasi.</p>
                <div class="flex gap-3 text-sm"><a href="{{ route('manufacturing.index') }}" class="text-indigo-700 underline">Master</a><a href="{{ route('manufacturing.production.index') }}" class="text-indigo-700 underline">Shop floor</a></div>
            </div>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">39.1 Rencana inspeksi</h3>
                <form method="POST" action="{{ route('manufacturing.quality.plans.store') }}" class="mt-3 grid gap-3 md:grid-cols-4 text-sm">
                    @csrf
                    <input name="name" placeholder="Nama rencana" required class="rounded border-gray-300">
                    <select name="stage" class="rounded border-gray-300"><option value="receiving">Penerimaan (GRN)</option><option value="in_process" selected>In-process</option><option value="final">Akhir (FG)</option></select>
                    <input name="characteristic_names" placeholder="Karakteristik (pisah koma)" required class="rounded border-gray-300">
                    <input name="spec_min" type="number" step="0.000001" placeholder="Batas bawah" class="rounded border-gray-300"><input name="spec_max" type="number" step="0.000001" placeholder="Batas atas" class="rounded border-gray-300">
                    <input name="aql_percent" type="number" step="0.01" value="1" required placeholder="AQL %" class="rounded border-gray-300"><input name="sample_size" type="number" min="1" value="5" required placeholder="Sampel" class="rounded border-gray-300">
                    <select name="frequency" class="rounded border-gray-300"><option value="per_batch">Per batch</option><option value="per_shift">Per shift</option><option value="per_order">Per order</option></select>
                    <button class="rounded bg-indigo-600 px-4 py-2 text-white">Simpan rencana</button>
                </form>
                <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">Nama</th><th class="p-2 text-left">Stage</th><th class="p-2 text-left">Spesifikasi</th><th class="p-2 text-right">AQL%</th><th class="p-2 text-right">Sampel</th></tr></thead><tbody>
                    @foreach ($plans as $plan)<tr class="border-t"><td class="p-2">{{ $plan->name }}</td><td class="p-2">{{ $plan->stage }}</td><td class="p-2">{{ $plan->spec_min }} – {{ $plan->spec_max }}</td><td class="p-2 text-right">{{ $plan->aql_percent }}</td><td class="p-2 text-right">{{ $plan->sample_size }}</td></tr>@endforeach
                </tbody></table></div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">39.2/39.8 Inspeksi & alat ukur</h3>
                <form method="POST" action="{{ route('manufacturing.quality.inspections.store') }}" class="mt-3 grid gap-3 md:grid-cols-4 text-sm">
                    @csrf
                    <select name="stage" class="rounded border-gray-300"><option value="receiving">receiving</option><option value="in_process" selected>in_process</option><option value="final">final</option></select>
                    <select name="subject_type" class="rounded border-gray-300"><option value="production_order">production_order</option><option value="grn">grn</option><option value="lot">lot</option></select>
                    <input name="subject_id" placeholder="ID subjek" required class="rounded border-gray-300">
                    <select name="plan_id" class="rounded border-gray-300"><option value="">Rencana (auto)</option>@foreach ($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach</select>
                    <select name="gauge_id" class="rounded border-gray-300"><option value="">Alat ukur (opsional)</option>@foreach ($gauges as $gauge)<option value="{{ $gauge->id }}" {{ $gauge->calibration_due && $gauge->calibration_due->isPast() ? 'disabled' : '' }}>{{ $gauge->code }}{{ $gauge->calibration_due && $gauge->calibration_due->isPast() ? ' (kadaluarsa)' : '' }}</option>@endforeach</select>
                    <input name="readings" placeholder="Pembacaan, pisah koma (mis. 10.1, 9.9)" required class="rounded border-gray-300">
                    <select name="lot_id" class="rounded border-gray-300"><option value="">Lot (opsional)</option>@foreach ($lots as $lot)<option value="{{ $lot->id }}">{{ $lot->lot_number }}</option>@endforeach</select>
                    <button class="rounded bg-indigo-600 px-3 py-2 text-white">Inspeksi</button>
                </form>
                <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">Stage</th><th class="p-2 text-left">Subjek</th><th class="p-2 text-left">Hasil</th><th class="p-2 text-left">Temuan</th><th class="p-2 text-left">Aksi</th></tr></thead><tbody>
                    @foreach ($inspections as $inspection)<tr class="border-t"><td class="p-2">{{ $inspection->stage }}</td><td class="p-2">{{ $inspection->subject_type }} #{{ substr($inspection->subject_id, 0, 8) }}</td><td class="p-2 {{ $inspection->result === 'failed' ? 'text-rose-600' : ($inspection->result === 'passed' ? 'text-emerald-600' : 'text-amber-600') }}">{{ $inspection->result }}</td><td class="p-2">{{ $inspection->findings }}</td><td class="p-2">@if ($inspection->result === 'failed')<form method="POST" action="{{ route('manufacturing.quality.inspections.waiver', $inspection) }}" class="flex gap-1">@csrf<input name="reason" placeholder="Alasan dispensasi" required class="w-40 rounded border-gray-300 text-xs"><button class="text-amber-700 underline">Dispensasi</button></form>@endif</td></tr>@endforeach
                </tbody></table></div>
                <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">Kode</th><th class="p-2 text-left">Nama</th><th class="p-2 text-left">Kalibrasi</th><th class="p-2 text-left">Status</th></tr></thead><tbody>
                    @foreach ($gauges as $gauge)<tr class="border-t"><td class="p-2">{{ $gauge->code }}</td><td class="p-2">{{ $gauge->name }}</td><td class="p-2">{{ $gauge->calibration_due?->format('Y-m-d') ?? '—' }}</td><td class="p-2 {{ $gauge->isCalibrationValid() ? 'text-emerald-600' : 'text-rose-600' }}">{{ $gauge->isCalibrationValid() ? 'valid' : 'kedaluwarsa/aktifkan' }}</td></tr>@endforeach
                </tbody></table></div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">39.4 NCR → CAPA</h3>
                <form method="POST" action="{{ route('manufacturing.quality.ncrs.store') }}" class="mt-3 grid gap-3 md:grid-cols-4 text-sm">
                    @csrf
                    <input name="title" placeholder="Judul ketidaksesuaian" required class="rounded border-gray-300">
                    <select name="severity" class="rounded border-gray-300"><option value="minor">Minor</option><option value="major">Major</option><option value="critical">Critical</option></select>
                    <select name="source" class="rounded border-gray-300"><option value="inspection">inspection</option><option value="scrap">scrap</option><option value="supplier">supplier</option></select>
                    <input name="due_date" type="date" class="rounded border-gray-300">
                    <input name="description" placeholder="Keterangan" class="md:col-span-3 rounded border-gray-300">
                    <button class="rounded bg-rose-600 px-4 py-2 text-white">Buka NCR</button>
                </form>
                <div class="mt-3 space-y-2">
                    @foreach ($ncrs as $ncr)
                        <div class="rounded border p-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span><strong>{{ $ncr->number }}</strong> — {{ $ncr->title }} ({{ $ncr->severity }}, {{ $ncr->status }}){{ $ncr->scar_ref ? ' · '.$ncr->scar_ref : '' }}</span>
                                <span class="text-xs text-gray-500">jatuh tempo {{ $ncr->due_date?->format('Y-m-d') }}</span>
                            </div>
                            <form method="POST" action="{{ route('manufacturing.quality.capas.store', $ncr) }}" class="mt-2 flex flex-wrap gap-2">
                                @csrf
                                <select name="kind" class="rounded border-gray-300 text-xs"><option value="corrective">Korektif</option><option value="preventive">Preventif</option></select>
                                <input name="action" placeholder="Tindakan" required class="w-64 rounded border-gray-300 text-xs"><input name="due_date" type="date" required class="rounded border-gray-300 text-xs">
                                <button class="rounded bg-indigo-600 px-2 py-1 text-xs text-white">+ CAPA</button>
                            </form>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">NCR</th><th class="p-2 text-left">Jenis</th><th class="p-2 text-left">Tindakan</th><th class="p-2 text-left">Tenggat</th><th class="p-2 text-left">Status</th><th class="p-2 text-left">Aksi</th></tr></thead><tbody>
                        @foreach ($capas as $capa)<tr class="border-t"><td class="p-2">{{ $capa->ncr?->number }}</td><td class="p-2">{{ $capa->kind }}</td><td class="p-2">{{ \Illuminate\Support\Str::limit($capa->action, 60) }}</td><td class="p-2 {{ $capa->isOverdue() ? 'text-rose-600' : '' }}">{{ $capa->due_date?->format('Y-m-d') }}</td><td class="p-2">{{ $capa->status }}</td><td class="p-2">@if ($capa->status === 'open')<form method="POST" action="{{ route('manufacturing.quality.capas.complete', $capa) }}" class="flex gap-1">@csrf<select name="effectiveness" class="rounded border-gray-300 text-xs"><option value="effective">Efektif</option><option value="ineffective">Tidak</option></select><input name="note" placeholder="Catatan verifikasi" required class="w-40 rounded border-gray-300 text-xs"><button class="text-emerald-700 underline text-xs">Verifikasi</button></form>@endif</td></tr>@endforeach
                    </tbody></table></div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">39.5/39.6/39.7 Lot: trace, sertifikat, recall</h3>
                <div class="mt-3 space-y-2">
                    @foreach ($lots as $lot)
                        <div class="flex flex-wrap items-center justify-between gap-2 rounded border p-3 text-sm">
                            <span>{{ $lot->lot_number }} — {{ $lot->material?->code }} · {{ $lot->qty }} · {{ $lot->status }}</span>
                            <span class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('manufacturing.quality.lots.trace', $lot) }}" class="text-indigo-700 underline">Trace</a>
                                <form method="POST" action="{{ route('manufacturing.quality.lots.certificates', $lot) }}" class="flex gap-1">@csrf<select name="type" class="rounded border-gray-300 text-xs"><option value="coa">COA</option><option value="coc">COC</option><option value="sni">SNI</option><option value="halal">Halal</option><option value="bpom">BPOM</option><option value="gmp">GMP</option></select><input name="number" placeholder="No. sertifikat" required class="w-32 rounded border-gray-300 text-xs"><input name="expires_at" type="date" class="rounded border-gray-300 text-xs"><button class="text-indigo-700 underline text-xs">+ Sertifikat</button></form>
                                @if ($lot->status !== 'active')<form method="POST" action="{{ route('manufacturing.quality.lots.release', $lot) }}">@csrf<button class="text-emerald-700 underline">Lepaskan</button></form>@endif
                                <form method="POST" action="{{ route('manufacturing.quality.lots.recall', $lot) }}" class="flex gap-1">@csrf<input name="reason" placeholder="Alasan recall" required class="w-40 rounded border-gray-300 text-xs"><button class="text-rose-700 underline text-xs">Recall</button></form>
                            </span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 space-y-2">
                    @foreach ($recalls as $recall)
                        <div class="flex flex-wrap items-center justify-between gap-2 rounded border border-rose-200 bg-rose-50 p-3 text-sm">
                            <span>Recall {{ substr($recall->id, 0, 8) }} — lot {{ $recall->lot?->lot_number }} · {{ $recall->status }} · {{ $recall->recipients()->count() }} penerima · biaya {{ number_format($recall->cost_idr) }}</span>
                            <span class="flex gap-2">
                                @if ($recall->status === 'planned')<form method="POST" action="{{ route('manufacturing.quality.recalls.notify', $recall) }}">@csrf<button class="text-indigo-700 underline">Notifikasi</button></form>@endif
                                @if ($recall->status !== 'completed')<form method="POST" action="{{ route('manufacturing.quality.recalls.complete', $recall) }}" class="flex gap-1">@csrf<input name="cost_idr" type="number" min="0" value="0" placeholder="Biaya" class="w-24 rounded border-gray-300 text-xs"><input name="destruction_note" placeholder="Penghancuran" required class="w-48 rounded border-gray-300 text-xs"><button class="text-rose-700 underline text-xs">Selesai</button></form>@endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
