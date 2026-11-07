@props(['label', 'value', 'delta' => null, 'deltaPositive' => true])
<div class="bg-white overflow-hidden rounded-lg border border-slate-200 px-4 py-4 sm:p-5">
    <dt class="truncate text-xs font-medium text-slate-500">{{ $label }}</dt>
    <dd class="mt-1 flex items-baseline justify-between md:block lg:flex">
        <div class="flex items-baseline text-2xl font-bold tracking-tight text-slate-900 tabular-nums">
            {{ $value }}
        </div>
        @if($delta)
            <div class="inline-flex items-baseline rounded-full px-2.5 py-0.5 text-xs font-medium {{ $deltaPositive ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                {{ $delta }}
            </div>
        @endif
    </dd>
</div>
