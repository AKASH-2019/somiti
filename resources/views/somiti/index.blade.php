<x-layouts.app title="সমিতি">
    <x-page-header title="সমিতি অ্যাকাউন্ট" description="শেয়ারভিত্তিক দৈনিক সঞ্চয়">
        <x-slot:actions>
            <a href="{{ route($rp . '.register') }}" class="btn-quiet">মাসিক রেজিস্টার</a>
            <a href="{{ route($rp . '.create') }}" class="btn-primary">নতুন সমিতি অ্যাকাউন্ট</a>
        </x-slot:actions>
    </x-page-header>

    <section class="panel mb-6 grid grid-cols-3 divide-x divide-rule">
        <x-stat label="চালু অ্যাকাউন্ট" :value="bn_digits($totals->accounts ?? 0)" />
        <x-stat label="মোট শেয়ার" :value="bn_digits($totals->shares ?? 0)" />
        <x-stat label="দৈনিক প্রত্যাশিত জমা" :value="bn_money($totals->daily ?? 0)" />
    </section>

    <x-search-bar placeholder="অ্যাকাউন্ট নম্বর, সদস্যের নাম, আইডি বা মোবাইল">
        @if ($collectors->isNotEmpty())
            <div>
                <label for="collector" class="sr-only">কালেক্টর</label>
                <select id="collector" name="collector" class="field w-auto">
                    <option value="">সব কালেক্টর</option>
                    @foreach ($collectors as $c)
                        <option value="{{ $c->id }}" @selected(request('collector') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </x-search-bar>

    <div class="panel overflow-hidden">
        @if ($accounts->isEmpty())
            <p class="px-5 py-8 text-center text-ink-soft">
                কোনো সমিতি অ্যাকাউন্ট পাওয়া যায়নি। <a href="{{ route($rp . '.create') }}" class="font-semibold text-ledger-700 underline">নতুন অ্যাকাউন্ট খুলুন</a>।
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="register">
                    <thead>
                    <tr>
                        <th>অ্যাকাউন্ট নম্বর</th><th>সদস্য</th><th>প্রতিষ্ঠান</th>
                        <th class="num">শেয়ার</th><th class="num">দৈনিক জমা</th><th>শুরুর তারিখ</th>
                        @if ($collectors->isNotEmpty())<th>কালেক্টর</th>@endif
                        <th>অবস্থা</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($accounts as $a)
                        <tr>
                            <td class="whitespace-nowrap font-semibold">{{ $a->account_no }}</td>
                            <td>{{ $a->member->name }} <span class="block text-sm text-ink-soft">{{ $a->member->member_code }}</span></td>
                            <td>{{ $a->member->business_name ?: '—' }}</td>
                            <td class="num">{{ bn_digits($a->somitiDetail->number_of_shares) }}</td>
                            <td class="num whitespace-nowrap">{{ bn_money($a->somitiDetail->daily_deposit_amount) }}</td>
                            <td class="whitespace-nowrap">{{ bn_date($a->somitiDetail->start_date) }}</td>
                            @if ($collectors->isNotEmpty())<td>{{ $a->collector?->name ?? '—' }}</td>@endif
                            <td><x-status-badge :tone="$a->isActive() ? 'success' : 'muted'">{{ $a->status->label() }}</x-status-badge></td>
                            <td class="text-right"><a href="{{ route($rp . '.show', $a) }}" class="text-sm font-semibold text-ledger-700 hover:underline">বিস্তারিত</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    {{ $accounts->links() }}
</x-layouts.app>
