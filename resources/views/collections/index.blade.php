@php
    $role = auth()->user()->isAdmin() ? 'admin' : 'collector';
    $isSomiti = $type === \App\Enums\AccountType::Somiti;
    $title = $isSomiti ? 'সমিতি সংগ্রহ' : 'কিস্তি সংগ্রহ';
@endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" :description="$isSomiti ? 'দৈনিক সঞ্চয়ের টাকা নিন' : 'ঋণের কিস্তি নিন'">
        <x-slot:actions>
            <a href="{{ route($role . '.transactions.index', ['type' => $type->value]) }}" class="btn-quiet">লেনদেন তালিকা</a>
        </x-slot:actions>
    </x-page-header>

    <nav class="no-print mb-5 flex gap-1 border-b border-rule" aria-label="সংগ্রহের ধরন">
        @foreach (['somiti' => 'সমিতি', 'kisti' => 'কিস্তি'] as $value => $label)
            <a href="{{ route($rp . '.' . $value, request()->only('q')) }}"
               @class([
                   '-mb-px border-b-2 px-4 py-2 text-[15px]',
                   'border-ledger-700 font-semibold text-ledger-900' => $type->value === $value,
                   'border-transparent text-ink-soft hover:text-ink' => $type->value !== $value,
               ])
               @if ($type->value === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <section class="panel mb-6 grid grid-cols-3 divide-x divide-rule">
        <x-stat label="আজ আমার সংগ্রহ" :value="bn_money($todayTotal)" :note="bn_digits($todayCount) . 'টি রসিদ (সমিতি ও কিস্তি)'" />
        <x-stat label="আমার হাতে থাকা টাকা" :value="bn_money($cashBalance)" />
        <x-stat label="আজ" :value="bn_date(today())" :note="\App\Support\BanglaFormatter::DAYS[today()->dayOfWeek]" />
    </section>

    <x-search-bar placeholder="সদস্যের নাম, আইডি, মোবাইল বা অ্যাকাউন্ট নম্বর" />

    <div class="panel overflow-hidden">
        @if ($accounts->isEmpty())
            <p class="px-5 py-8 text-center text-ink-soft">কোনো চালু {{ $isSomiti ? 'সমিতি' : 'কিস্তি' }} অ্যাকাউন্ট পাওয়া যায়নি।</p>
        @else
            <div class="overflow-x-auto">
                <table class="register">
                    <thead>
                    <tr>
                        <th>সদস্য</th><th>অ্যাকাউন্ট</th>
                        @if ($isSomiti)
                            <th class="num">শেয়ার</th><th class="num">দৈনিক জমা</th>
                        @else
                            <th>ধরন</th><th class="num">কিস্তি</th>
                        @endif
                        <th class="num">বকেয়া</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($accounts as $a)
                        @php($due = $dues[$a->id] ?? null)
                        <tr>
                            <td>
                                <span class="font-semibold">{{ $a->member->name }}</span>
                                <span class="block text-sm text-ink-soft">{{ $a->member->member_code }} · {{ bn_digits($a->member->mobile) }}</span>
                            </td>
                            <td class="whitespace-nowrap">{{ $a->account_no }}</td>
                            @if ($isSomiti)
                                <td class="num">{{ bn_digits($a->somitiDetail->number_of_shares) }}</td>
                                <td class="num whitespace-nowrap">{{ bn_money($a->somitiDetail->daily_deposit_amount) }}</td>
                            @else
                                <td>{{ $a->kistiDetail->frequency->label() }}</td>
                                <td class="num whitespace-nowrap">{{ bn_money($a->kistiDetail->installment_amount) }}</td>
                            @endif
                            <td class="num whitespace-nowrap">
                                @if ($due && (float) $due->due > 0)
                                    <span class="font-semibold text-danger">{{ bn_money($due->due) }}</span>
                                    <span class="block text-xs text-ink-soft">{{ bn_digits($due->n) }}{{ $isSomiti ? ' দিন' : 'টি কিস্তি' }}</span>
                                @else
                                    <span class="text-ledger-700">নেই</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route($rp . '.create', $a) }}" class="btn-primary px-3 py-1.5 text-sm">টাকা নিন</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    {{ $accounts->links() }}
</x-layouts.app>
