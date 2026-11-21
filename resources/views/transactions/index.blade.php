<x-layouts.app title="লেনদেন তালিকা">
    <x-page-header title="লেনদেন তালিকা"
                   :description="($from->isSameDay($to) ? bn_date($from) : bn_date($from) . ' – ' . bn_date($to)) . ' — ' . bn_digits($count) . 'টি সম্পন্ন লেনদেন, মোট ' . bn_money($total)">
        <x-slot:actions>
            <button type="button" class="btn-quiet" onclick="window.print()">প্রিন্ট করুন</button>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="no-print mb-4 flex flex-wrap items-end gap-2">
        <div>
            <label for="from" class="field-label">শুরু</label>
            <input id="from" name="from" type="date" class="field" value="{{ $from->toDateString() }}">
        </div>
        <div>
            <label for="to" class="field-label">শেষ</label>
            <input id="to" name="to" type="date" class="field" value="{{ $to->toDateString() }}">
        </div>
        <div>
            <label for="type" class="field-label">ধরন</label>
            <select id="type" name="type" class="field w-auto">
                <option value="">সব</option>
                <option value="somiti" @selected(request('type') === 'somiti')>সমিতি</option>
                <option value="kisti" @selected(request('type') === 'kisti')>কিস্তি</option>
            </select>
        </div>
        <div>
            <label for="status" class="field-label">অবস্থা</label>
            <select id="status" name="status" class="field w-auto">
                <option value="">সব</option>
                <option value="completed" @selected(request('status') === 'completed')>সম্পন্ন</option>
                <option value="reversed" @selected(request('status') === 'reversed')>বাতিলকৃত</option>
            </select>
        </div>
        @if ($collectors->isNotEmpty())
            <div>
                <label for="collector" class="field-label">গ্রহণকারী</label>
                <select id="collector" name="collector" class="field w-auto">
                    <option value="">সবাই</option>
                    @foreach ($collectors as $c)
                        <option value="{{ $c->id }}" @selected(request('collector') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="min-w-48 flex-1">
            <label for="q" class="field-label">রসিদ নম্বর / সদস্য</label>
            <input id="q" name="q" type="search" class="field" value="{{ request('q') }}">
        </div>
        <button type="submit" class="btn-primary">দেখুন</button>
    </form>

    <div class="panel overflow-hidden">
        @if ($transactions->isEmpty())
            <p class="px-5 py-8 text-center text-ink-soft">এই সময়ে কোনো লেনদেন নেই।</p>
        @else
            <div class="overflow-x-auto">
                <table class="register">
                    <thead>
                    <tr><th>রসিদ নম্বর</th><th>সময়</th><th>সদস্য</th><th>অ্যাকাউন্ট</th><th>গ্রহণকারী</th><th class="num">টাকা</th><th>অবস্থা</th><th class="no-print"></th></tr>
                    </thead>
                    <tbody>
                    @foreach ($transactions as $t)
                        <tr @class(['text-ink-soft line-through decoration-danger/40' => $t->isReversed()])>
                            <td class="whitespace-nowrap font-semibold">{{ $t->receipt_no }}</td>
                            <td class="whitespace-nowrap">{{ bn_datetime($t->collected_at) }}</td>
                            <td>{{ $t->member->name }} <span class="block text-xs text-ink-soft">{{ $t->member->member_code }}</span></td>
                            <td class="whitespace-nowrap">{{ $t->account->account_no }} <span class="text-xs text-ink-soft">{{ $t->account->type->label() }}</span></td>
                            <td>{{ $t->collector->name }}</td>
                            <td class="num whitespace-nowrap">{{ bn_money($t->amount) }}</td>
                            <td>
                                @if ($t->isReversed())
                                    <x-status-badge tone="danger">বাতিলকৃত</x-status-badge>
                                @elseif ($t->entry_type === \App\Enums\EntryType::Historical)
                                    <x-status-badge tone="pending">পুরনো খাতা</x-status-badge>
                                @else
                                    <x-status-badge tone="success">সম্পন্ন</x-status-badge>
                                @endif
                            </td>
                            <td class="no-print text-right"><a href="{{ route($rp . '.show', $t) }}" class="text-sm font-semibold text-ledger-700 hover:underline">রসিদ</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    {{ $transactions->links() }}
</x-layouts.app>
