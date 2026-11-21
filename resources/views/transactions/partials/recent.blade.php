{{-- Last transactions on an account. $recent (collection of Transaction) --}}
@php($role = auth()->user()->isAdmin() ? 'admin' : 'collector')
<section class="panel mt-6 overflow-hidden">
    <div class="flex items-center justify-between border-b border-rule px-4 py-3">
        <h2 class="text-lg font-bold text-ledger-900">সাম্প্রতিক লেনদেন</h2>
    </div>
    @if ($recent->isEmpty())
        <p class="px-4 py-6 text-ink-soft">এখনো কোনো টাকা জমা হয়নি।</p>
    @else
        <div class="overflow-x-auto">
            <table class="register">
                <thead><tr><th>রসিদ</th><th>সময়</th><th>গ্রহণকারী</th><th class="num">টাকা</th><th>অবস্থা</th></tr></thead>
                <tbody>
                @foreach ($recent as $t)
                    <tr @class(['text-ink-soft' => $t->isReversed()])>
                        <td><a href="{{ route($role . '.transactions.show', $t) }}" class="font-semibold text-ledger-700 hover:underline">{{ $t->receipt_no }}</a></td>
                        <td class="whitespace-nowrap">{{ bn_datetime($t->collected_at) }}</td>
                        <td>{{ $t->collector->name }}</td>
                        <td class="num">{{ bn_money($t->amount) }}</td>
                        <td>
                            @if ($t->isReversed())<x-status-badge tone="danger">বাতিলকৃত</x-status-badge>
                            @elseif ($t->entry_type === \App\Enums\EntryType::Historical)<x-status-badge tone="pending">পুরনো খাতা</x-status-badge>
                            @else<x-status-badge tone="success">সম্পন্ন</x-status-badge>@endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
