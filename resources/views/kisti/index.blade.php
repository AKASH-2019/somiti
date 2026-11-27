<x-layouts.app title="কিস্তি">
    <x-page-header title="কিস্তি অ্যাকাউন্ট" description="দৈনিক ও সাপ্তাহিক ঋণ পরিশোধ">
        <x-slot:actions>
            <a href="{{ route($rp . '.create', ['new' => 1]) }}" class="btn-quiet">নতুন সদস্যকে কিস্তি</a>
            <a href="{{ route($rp . '.create') }}" class="btn-primary">বিদ্যমান সদস্যকে কিস্তি</a>
        </x-slot:actions>
    </x-page-header>

    <section class="panel mb-6 grid grid-cols-2 lg:grid-cols-4 lg:divide-x lg:divide-rule">
        <x-stat label="অ্যাকাউন্ট" :value="bn_digits($totals['count'])" />
        <x-stat label="মোট ঋণ" :value="bn_money($totals['loan'])" />
        <x-stat label="মোট আদায়" :value="bn_money($totals['paid'])" :note="'পরিশোধযোগ্য ' . bn_money($totals['payable'])" />
        <x-stat label="অবশিষ্ট" :value="bn_money($totals['remaining'])" />
    </section>

    <x-search-bar placeholder="অ্যাকাউন্ট নম্বর, সদস্যের নাম, আইডি বা মোবাইল">
        <div>
            <label for="status" class="sr-only">অবস্থা</label>
            <select id="status" name="status" class="field w-auto">
                <option value="active" @selected($status === 'active')>চালু</option>
                <option value="closed" @selected($status === 'closed')>পরিশোধিত/বন্ধ</option>
                <option value="settled" @selected($status === 'settled')>সেটেলমেন্টে বন্ধ</option>
                <option value="cancelled" @selected($status === 'cancelled')>বাতিল</option>
            </select>
        </div>
        <div>
            <label for="frequency" class="sr-only">কিস্তির ধরন</label>
            <select id="frequency" name="frequency" class="field w-auto">
                <option value="">দৈনিক ও সাপ্তাহিক</option>
                <option value="daily" @selected(request('frequency') === 'daily')>দৈনিক</option>
                <option value="weekly" @selected(request('frequency') === 'weekly')>সাপ্তাহিক</option>
            </select>
        </div>
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
            <p class="px-5 py-8 text-center text-ink-soft">কোনো কিস্তি অ্যাকাউন্ট পাওয়া যায়নি।</p>
        @else
            <div class="overflow-x-auto">
                <table class="register">
                    <thead>
                    <tr>
                        <th>অ্যাকাউন্ট নম্বর</th><th>সদস্য</th><th>ধরন</th>
                        <th class="num">ঋণ</th><th class="num">মোট পরিশোধযোগ্য</th><th class="num">কিস্তি</th>
                        <th class="num">আদায়</th><th class="num">অবশিষ্ট</th>
                        @if ($collectors->isNotEmpty())<th>কালেক্টর</th>@endif
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($accounts as $a)
                        @php($k = $a->kistiDetail)
                        @php($paid = number_format((float) $a->paid_sum, 2, '.', ''))
                        <tr>
                            <td class="whitespace-nowrap font-semibold">{{ $a->account_no }}</td>
                            <td>{{ $a->member->name }} <span class="block text-sm text-ink-soft">{{ $a->member->member_code }}</span></td>
                            <td>{{ $k->frequency->label() }}</td>
                            <td class="num whitespace-nowrap">{{ bn_money($k->loan_amount) }}</td>
                            <td class="num whitespace-nowrap">{{ bn_money($k->total_payable) }}</td>
                            <td class="num whitespace-nowrap">{{ bn_money($k->installment_amount) }} × {{ bn_digits($k->total_installments) }}</td>
                            <td class="num whitespace-nowrap">{{ bn_money($paid) }}</td>
                            <td class="num whitespace-nowrap">{{ bn_money(\App\Support\Money::sub(\App\Support\Money::sub((string) $k->total_payable, $paid), number_format((float) $a->waived_sum, 2, '.', ''))) }}</td>
                            @if ($collectors->isNotEmpty())<td>{{ $a->collector?->name ?? '—' }}</td>@endif
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
