<x-ds.shell title="Pusat Pertukaran Data EDI">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'API v2', 'href' => route('admin.api.clients')],
                    ['label' => 'Pertukaran EDI', 'href' => route('admin.api.edi')],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Interchange EDI (UN/EDIFACT & ASC X12)</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pengawasan siklus dokumen EDI 8 status (received hingga posted/archived) dan bukti CONTRL/997.</p>
            </div>
        </div>

        @include('core::api._states')

        <x-ds.card title="Daftar Interchange EDI">
            @if ($interchanges->isEmpty())
                <x-ds.empty
                    title="Belum Ada Interchange EDI"
                    description="Belum ada berkas ORDERS, INVOIC, atau DESADV yang diproses."
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Kontrol Ref</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Standar / Tipe</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Pengirim / Penerima</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">No. Dokumen</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Status SM</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Target Handler</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($interchanges as $i)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $i->control_ref }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $i->standard }} / {{ $i->message_type }}</td>
                                    <td class="px-4 py-2 text-xs">{{ $i->sender_ref }} &rarr; {{ $i->receiver_ref }}</td>
                                    <td class="px-4 py-2 font-medium">{{ $i->document_number ?: '-' }}</td>
                                    <td class="px-4 py-2">
                                        <x-ds.badge :variant="in_array($i->status, ['posted', 'acknowledged']) ? 'success' : ($i->status === 'rejected' ? 'danger' : 'neutral')">
                                            {{ $i->status }}
                                        </x-ds.badge>
                                    </td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $i->handler_module ? $i->handler_module . ':' . $i->target_reference : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ds.card>
    </div>
</x-ds.shell>

