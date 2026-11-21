@props(['label', 'value', 'note' => null])
<div {{ $attributes->merge(['class' => 'px-4 py-3']) }}>
    <p class="text-sm text-ink-soft">{{ $label }}</p>
    <p class="mt-0.5 text-2xl font-bold tabular-nums text-ledger-900">{{ $value }}</p>
    @if ($note)
        <p class="text-sm text-ink-soft">{{ $note }}</p>
    @endif
</div>
