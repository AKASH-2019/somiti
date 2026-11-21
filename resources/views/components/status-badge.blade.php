{{-- tone: success | pending | danger | muted --}}
@props(['tone' => 'muted'])
@php
    $classes = [
        'success' => 'bg-ledger-100 text-ledger-800',
        'pending' => 'bg-pending-50 text-pending',
        'danger' => 'bg-danger-50 text-danger',
        'muted' => 'bg-paper text-ink-soft border border-rule',
    ][$tone] ?? 'bg-paper text-ink-soft';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded px-2 py-0.5 text-sm font-medium $classes"]) }}>{{ $slot }}</span>
