@props([
    'headers' => [],
])

<div class="overflow-hidden rounded-2xl border border-slate-700/50 bg-slate-800/80 backdrop-blur-xl shadow-xl">
    <div class="overflow-x-auto">
        <table {{ $attributes->merge(['class' => 'w-full text-left border-collapse text-sm']) }}>
            @if(isset($header) || !empty($headers))
                <thead class="bg-slate-900/60 border-b border-slate-700/60 text-xs font-semibold uppercase tracking-wider text-slate-400">
                    @if(isset($header))
                        {{ $header }}
                    @else
                        <tr>
                            @foreach($headers as $h)
                                <th scope="col" class="px-5 py-3.5">{{ $h }}</th>
                            @endforeach
                        </tr>
                    @endif
                </thead>
            @endif

            <tbody class="divide-y divide-slate-700/40 text-slate-200">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @if(isset($pagination))
        <div class="px-5 py-3 border-t border-slate-700/50 bg-slate-900/40">
            {{ $pagination }}
        </div>
    @endif
</div>
