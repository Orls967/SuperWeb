<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Distributed Trace') }}: {{ $trace['correlation_id'] }}
        </h2>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="p-6 bg-white shadow rounded-lg">
                <h4 class="font-semibold text-gray-800 mb-4">Lompatan Layanan (Hops)</h4>
                <div class="space-y-3">
                    @foreach($trace['hops'] as $hop)
                        <div class="flex items-center space-x-4 p-3 bg-slate-50 rounded border">
                            <span class="font-mono text-sm font-semibold text-sky-700">{{ $hop['service'] }}</span>
                            <span class="text-xs text-gray-500">{{ $hop['timestamp'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

