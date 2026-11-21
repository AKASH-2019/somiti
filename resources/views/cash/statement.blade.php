<x-layouts.app :title="'ক্যাশ হিসাব — ' . $holder->name">
    <x-page-header :title="$adminView ? 'ক্যাশ বিবরণী — ' . $holder->name : 'আমার ক্যাশ হিসাব'"
                   :description="bn_date($from) . ' – ' . bn_date($to)">
        <x-slot:actions>
            @if ($adminView)
                <a href="{{ route('admin.cash.index') }}" class="btn-quiet">সবার হিসাব</a>
                <x-modal title="ক্যাশ সমন্বয়" :open="$errors->adjust->any()">
                    <x-slot:trigger><button type="button" class="btn-quiet">ক্যাশ সমন্বয়</button></x-slot:trigger>
                    <form method="POST" action="{{ route('admin.cash.adjust', $holder) }}" class="space-y-4">
                        @csrf
                        <p class="text-[15px] text-ink-soft">গণনার ভুল ইত্যাদি সংশোধনের জন্য। লেজারে আলাদা এন্ট্রি হবে এবং কারণসহ অডিট লগে থাকবে।</p>
                        <div class="flex gap-2">
                            <label class="flex flex-1 items-center gap-2 rounded-md border border-rule px-3 py-2 has-[:checked]:border-ledger-600 has-[:checked]:bg-ledger-100"><input type="radio" name="direction" value="in" required @checked(old('direction') === 'in')> যোগ করুন</label>
                            <label class="flex flex-1 items-center gap-2 rounded-md border border-rule px-3 py-2 has-[:checked]:border-ledger-600 has-[:checked]:bg-ledger-100"><input type="radio" name="direction" value="out" @checked(old('direction') === 'out')> বাদ দিন</label>
                        </div>
                        @error('direction', 'adjust') <p class="field-error">{{ $message }}</p> @enderror
                        <x-field name="amount" label="পরিমাণ (৳)" type="number" step="0.01" min="0.01" bag="adjust" required />
                        <x-field name="reason" label="কারণ" bag="adjust" required />
                        <div class="flex justify-end gap-2">
                            <button type="button" class="btn-quiet" @click="open = false">বাতিল</button>
                            <button type="submit" class="btn-primary">সংরক্ষণ করুন</button>
                        </div>
                    </form>
                </x-modal>
            @endif
            <button type="button" class="btn-quiet" onclick="window.print()">প্রিন্ট করুন</button>
        </x-slot:actions>
    </x-page-header>

    <section class="panel mb-6 grid grid-cols-2 lg:grid-cols-5 lg:divide-x lg:divide-rule">
        <x-stat label="শুরুর ব্যালেন্স" :value="bn_money($st['opening'])" />
        <x-stat label="এসেছে" :value="bn_money($st['in'])" />
        <x-stat label="গেছে" :value="bn_money($st['out'])" />
        <x-stat label="শেষ ব্যালেন্স" :value="bn_money($st['closing'])" />
        <x-stat label="হস্তান্তরযোগ্য (এখন)" :value="bn_money($available)" />
    </section>

    <form method="GET" class="no-print mb-4 flex flex-wrap items-end gap-2">
        <div><label for="from" class="field-label">শুরু</label><input id="from" name="from" type="date" class="field" value="{{ $from->toDateString() }}"></div>
        <div><label for="to" class="field-label">শেষ</label><input id="to" name="to" type="date" class="field" value="{{ $to->toDateString() }}"></div>
        <button type="submit" class="btn-primary">দেখুন</button>
    </form>

    <div class="panel overflow-x-auto">
        <table class="register">
            <thead>
            <tr><th>তারিখ ও সময়</th><th>ধরন</th><th>বিবরণ</th><th class="num">এসেছে</th><th class="num">গেছে</th><th class="num">ব্যালেন্স</th></tr>
            </thead>
            <tbody>
            <tr class="bg-paper">
                <td colspan="5" class="font-semibold">শুরুর ব্যালেন্স ({{ bn_date($from) }})</td>
                <td class="num font-semibold">{{ bn_money($st['opening']) }}</td>
            </tr>
            @forelse ($st['rows'] as $e)
                <tr>
                    <td class="whitespace-nowrap">{{ bn_datetime($e->transaction_datetime) }}</td>
                    <td class="whitespace-nowrap">{{ $e->transaction_type->label() }}</td>
                    <td>{{ $e->description }}</td>
                    <td class="num whitespace-nowrap text-ledger-700">{{ \App\Support\Money::toPaisa((string) $e->credit) > 0 ? bn_money($e->credit) : '' }}</td>
                    <td class="num whitespace-nowrap text-danger">{{ \App\Support\Money::toPaisa((string) $e->debit) > 0 ? bn_money($e->debit) : '' }}</td>
                    <td class="num whitespace-nowrap font-semibold">{{ bn_money($e->running) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-ink-soft">এই সময়ে কোনো লেনদেন নেই।</td></tr>
            @endforelse
            <tr class="bg-paper">
                <td colspan="5" class="font-semibold">শেষ ব্যালেন্স ({{ bn_date($to) }})</td>
                <td class="num font-semibold">{{ bn_money($st['closing']) }}</td>
            </tr>
            </tbody>
        </table>
    </div>
</x-layouts.app>
