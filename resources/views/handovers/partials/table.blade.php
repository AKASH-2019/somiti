{{-- $handovers (paginator or collection), $rp --}}
@php($me = auth()->id())
<div class="overflow-x-auto">
    <table class="register">
        <thead>
        <tr><th>হস্তান্তর নম্বর</th><th>তারিখ ও সময়</th><th>প্রেরক</th><th>প্রাপক</th><th class="num">টাকা</th><th>অবস্থা</th><th class="no-print"></th></tr>
        </thead>
        <tbody>
        @foreach ($handovers as $h)
            <tr>
                <td class="whitespace-nowrap font-semibold">{{ $h->handover_number }}</td>
                <td class="whitespace-nowrap">{{ bn_datetime($h->handover_datetime) }}</td>
                <td>{{ $h->sender->name }}@if ($h->sender_collector_id === $me) <span class="text-xs text-ink-soft">(আমি)</span>@endif</td>
                <td>{{ $h->receiver->name }}@if ($h->receiver_collector_id === $me) <span class="text-xs text-ink-soft">(আমি)</span>@endif</td>
                <td class="num whitespace-nowrap font-semibold">{{ bn_money($h->amount) }}</td>
                <td><x-status-badge :tone="$h->status->tone()">{{ $h->status->label() }}</x-status-badge></td>
                <td class="no-print text-right"><a href="{{ route($rp . '.show', $h) }}" class="text-sm font-semibold text-ledger-700 hover:underline">বিস্তারিত</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
