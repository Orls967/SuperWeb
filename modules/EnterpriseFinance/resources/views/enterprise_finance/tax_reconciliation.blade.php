<x-ds.shell title="Rekonsiliasi Pajak Korporat & PPN Gapless">
    <div class="space-y-6">
        <x-ds.breadcrumb :items="[
            ['label' => 'Beranda', 'url' => '/dashboard'],
            ['label' => 'Enterprise Finance', 'url' => '/enterprise-finance'],
            ['label' => 'Pajak & PPN', 'url' => '#'],
        ]" />

        @if(session('status'))
            <x-ds.toast type="success" :message="session('status')" />
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-ds.stat label="Masa Pajak Aktif" value="{{ date('Y-m') }}" hint="Status rekonsiliasi berjalan" />
            <x-ds.stat label="Nomor Seri Faktur" value="Gapless 100%" hint="Tanpa celah urutan nomor" />
            <x-ds.stat label="Setoran Fiskal" value="IDR 300.000.000" hint="Kliring kas negara" />
        </div>

        <x-ds.card title="Riwayat Rekonsiliasi PPN & Setoran Kas Negara">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="p-3">Masa Pajak</th>
                            <th class="p-3">Entitas</th>
                            <th class="p-3 text-right">PPN Keluaran</th>
                            <th class="p-3 text-right">PPN Masukan</th>
                            <th class="p-3 text-right">Kurang Bayar</th>
                            <th class="p-3">Draf Nomor SPT</th>
                            <th class="p-3 text-center">Status</th>
                            <th class="p-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($summaries as $s)
                            <tr>
                                <td class="p-3 font-mono font-medium">{{ $s->period }}</td>
                                <td class="p-3">{{ $s->entity_code }}</td>
                                <td class="p-3 text-right tabular-nums">IDR {{ number_format($s->vat_output_minor, 0, ',', '.') }}</td>
                                <td class="p-3 text-right tabular-nums">IDR {{ number_format($s->vat_input_minor, 0, ',', '.') }}</td>
                                <td class="p-3 text-right tabular-nums font-semibold text-red-600">IDR {{ number_format($s->net_payable_minor, 0, ',', '.') }}</td>
                                <td class="p-3 font-mono text-xs">{{ $s->spt_draft_number ?? '-' }}</td>
                                <td class="p-3 text-center">
                                    <x-ds.badge-status :status="strtolower($s->settlement_status)" />
                                </td>
                                <td class="p-3 text-center">
                                    @if($s->settlement_status !== 'SETTLED')
                                        <form action="{{ route('enterprise_finance.tax.settle') }}" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="entity_code" value="{{ $s->entity_code }}" />
                                            <input type="hidden" name="period" value="{{ $s->period }}" />
                                            <x-ds.button type="submit" variant="secondary" size="sm">Setor PPN</x-ds.button>
                                        </form>
                                    @else
                                        <span class="text-xs text-green-600 font-medium">Lunas</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-4">
                                    <x-ds.empty title="Belum Ada Rekonsiliasi Pajak" description="Tidak ada data rekonsiliasi PPN atau PPh masa." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $summaries->links() }}
            </div>
        </x-ds.card>
    </div>
</x-ds.shell>

