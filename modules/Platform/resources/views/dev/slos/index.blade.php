<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Service Level Objectives (SLO)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('Error Budget Burn Rate') }}</h3>
                <p class="text-sm text-gray-600 mb-6">Pemantauan burn rate keandalan pilar bisnis secara real-time.</p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @forelse($slos as $slo)
                        <div class="border rounded-lg p-5 bg-white shadow-sm">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-bold text-gray-800">{{ $slo->service_name }}</span>
                                <span class="text-xs px-2 py-1 rounded bg-slate-100 text-slate-700">{{ $slo->tier }}</span>
                            </div>
                            <div class="text-2xl font-extrabold text-sky-600 mb-1">
                                {{ number_format($slo->target_sli_bps / 100, 2) }}%
                            </div>
                            <div class="text-xs text-gray-500 mb-4">Target Keandalan SLI</div>

                            <div class="space-y-1 text-xs text-gray-600 border-t pt-3">
                                <div class="flex justify-between">
                                    <span>Error Budget Bulanan:</span>
                                    <span class="font-medium">{{ $slo->error_budget_mins }} menit</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Burn Rate (1 jam):</span>
                                    <span class="font-bold {{ $slo->burn_rate_1h > 1.0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                        {{ $slo->burn_rate_1h }}x
                                    </span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-3 text-sm text-gray-500">Belum ada target SLO terkonfigurasi.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

