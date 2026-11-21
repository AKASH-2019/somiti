@php
    $isSomiti = $account->type === \App\Enums\AccountType::Somiti;
    $role = auth()->user()->isAdmin() ? 'admin' : 'collector';
    $oldKeys = array_map('strval', (array) old('keys', []));
@endphp
<x-layouts.app title="টাকা সংগ্রহ">
    <x-page-header :title="$account->member->name . ' — ' . $account->account_no"
                   :description="$isSomiti
                        ? 'সমিতি: ' . bn_digits($account->somitiDetail->number_of_shares) . ' শেয়ার, দৈনিক ' . bn_money($account->somitiDetail->daily_deposit_amount)
                        : 'কিস্তি: ' . $account->kistiDetail->frequency->label() . ' ' . bn_money($account->kistiDetail->installment_amount)">
        <x-slot:actions>
            <a href="{{ route($role . '.' . $account->type->value . '.show', $account) }}" class="btn-quiet">অ্যাকাউন্টের বিস্তারিত</a>
        </x-slot:actions>
    </x-page-header>

    @if (empty($items))
        <div class="panel max-w-2xl p-5 text-ink-soft">এই অ্যাকাউন্টে নেওয়ার মতো কোনো বকেয়া বা অগ্রিম দিন/কিস্তি নেই।</div>
    @else
    <form method="POST" action="{{ route($rp . '.store', $account) }}" class="grid gap-6 lg:grid-cols-5"
          x-data="collectionForm({ items: @js($items), somiti: @js($isSomiti), selected: @js($oldKeys), amount: @js(old('amount', '')) })"
          @submit="submitting = true">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        <template x-for="key in selected" :key="key"><input type="hidden" name="keys[]" :value="key"></template>

        <section class="panel overflow-hidden lg:col-span-3">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-rule px-4 py-3">
                <h2 class="text-lg font-bold text-ledger-900" x-text="somiti ? 'কোন দিনের জমা' : 'কোন কিস্তির জমা'"></h2>
                <div class="flex flex-wrap gap-1 text-sm">
                    <button type="button" class="btn-quiet px-2.5 py-1 text-sm" @click="selectDues()">সব বকেয়া</button>
                    <button type="button" class="btn-quiet px-2.5 py-1 text-sm" @click="selectToday()" x-show="hasToday()">শুধু আজ</button>
                    <button type="button" class="btn-quiet px-2.5 py-1 text-sm" @click="clear()">মুছুন</button>
                </div>
            </div>
            <p class="border-b border-rule bg-ledger-50 px-4 py-2 text-sm text-ink-soft">
                টাকার পরিমাণ লিখলে সবচেয়ে পুরনো বকেয়া থেকে নিজে থেকে বাছাই হবে। চাইলে টিক দিয়ে বদলে নিন।
            </p>
            <div class="max-h-[30rem] overflow-y-auto">
                <template x-for="(group, gi) in groups()" :key="gi">
                    <div>
                        <p class="sticky top-0 z-10 border-b border-rule bg-white px-4 py-1.5 text-sm font-semibold"
                           :class="{ 'text-danger': group.kind === 'previous', 'text-ledger-700': group.kind === 'current', 'text-ink-soft': group.kind === 'advance' }"
                           x-text="group.title + ' (' + bnDigits(group.items.length) + ')'"></p>
                        <template x-for="item in group.items" :key="item.key">
                            <label class="flex cursor-pointer items-center gap-3 border-b border-rule px-4 py-2 hover:bg-ledger-50"
                                   :class="isSelected(item.key) && 'bg-ledger-100'">
                                <input type="checkbox" class="h-4 w-4 rounded border-rule text-ledger-700" :checked="isSelected(item.key)" @change="toggle(item.key)">
                                <span class="flex-1">
                                    <span x-show="item.sequence" class="font-semibold" x-text="'কিস্তি নং ' + bnDigits(item.sequence) + ' — '"></span>
                                    <span x-text="item.label"></span>
                                    <span x-show="item.partial" class="ml-1 rounded bg-pending-50 px-1.5 text-xs text-pending">আংশিক জমা আছে</span>
                                </span>
                                <span class="tabular-nums" x-text="bnMoney(item.remaining)"></span>
                            </label>
                        </template>
                    </div>
                </template>
            </div>
        </section>

        <aside class="panel h-fit space-y-4 p-5 lg:col-span-2 lg:sticky lg:top-20">
            <div>
                <label for="amount" class="field-label">টাকার পরিমাণ (৳)</label>
                <input id="amount" name="amount" type="number" step="0.01" min="0.01" inputmode="decimal" class="field text-2xl font-bold"
                       x-model="amount" @input.debounce.300ms="autoSelect()" required autofocus>
                @error('amount') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <dl class="space-y-1.5 text-[15px]">
                <div class="flex justify-between"><dt class="text-ink-soft" x-text="somiti ? 'নির্বাচিত দিন' : 'নির্বাচিত কিস্তি'"></dt><dd x-text="bnDigits(selected.length) + 'টি'"></dd></div>
                <div class="flex justify-between"><dt class="text-ink-soft">নির্বাচিতদের মোট বাকি</dt><dd x-text="bnMoney(selectedTotal())"></dd></div>
                <template x-if="!somiti && partialNote()"><div class="rounded-md bg-pending-50 px-3 py-2 text-sm text-pending" x-text="partialNote()"></div></template>
            </dl>

            <p x-show="problem()" x-cloak class="rounded-md bg-danger-50 px-3 py-2 text-sm text-danger" x-text="problem()"></p>

            @if (auth()->user()->isAdmin())
                <fieldset class="rounded-md border border-rule p-3" x-data="{ historical: @js(old('entry_type') === 'historical') }">
                    <label class="flex items-start gap-2 text-[15px]">
                        <input type="checkbox" class="mt-1 h-4 w-4 rounded border-rule text-ledger-700" x-model="historical">
                        <span>পুরনো খাতার এন্ট্রি<span class="block text-sm text-ink-soft">আগে সংগ্রহ করা টাকা। কারও হাতের ক্যাশে যোগ হবে না।</span></span>
                    </label>
                    <input type="hidden" name="entry_type" :value="historical ? 'historical' : 'cash'">
                    <div x-show="historical" x-cloak class="mt-3">
                        <label for="collected_at" class="field-label">প্রকৃত জমার তারিখ</label>
                        <input id="collected_at" name="collected_at" type="date" class="field" :disabled="!historical" value="{{ old('collected_at') }}" max="{{ today()->toDateString() }}">
                        @error('collected_at') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </fieldset>
            @endif

            <div>
                <label for="notes" class="field-label">মন্তব্য</label>
                <input id="notes" name="notes" class="field" value="{{ old('notes') }}">
            </div>

            <button type="submit" class="btn-primary w-full py-3 text-lg" :disabled="submitting || !!problem() || !selected.length">
                <span x-text="submitting ? 'সংরক্ষণ হচ্ছে…' : 'জমা নিন ও রসিদ দিন'"></span>
            </button>
            <p class="text-center text-sm text-ink-soft">পেমেন্ট পদ্ধতি: নগদ · গ্রহণকারী: {{ auth()->user()->name }}</p>
        </aside>
    </form>

    <script>
        function collectionForm(init) {
            const paisa = (v) => Math.round((parseFloat(v) || 0) * 100);
            return {
                ...init,
                submitting: false,
                titles: { previous: 'পূর্ববর্তী বকেয়া', current: 'আজ', advance: 'অগ্রিম' },
                groups() {
                    return ['previous', 'current', 'advance']
                        .map((k) => ({ kind: k, title: this.titles[k], items: this.items.filter((i) => i.kind === k) }))
                        .filter((g) => g.items.length);
                },
                isSelected(key) { return this.selected.includes(key); },
                ordered(keys) { return this.items.filter((i) => keys.includes(i.key)).map((i) => i.key); },
                toggle(key) {
                    this.selected = this.isSelected(key) ? this.selected.filter((k) => k !== key) : this.ordered([...this.selected, key]);
                    this.amount = (this.selectedPaisa() / 100).toFixed(2).replace(/\.00$/, '');
                },
                selectedItems() { return this.items.filter((i) => this.selected.includes(i.key)); },
                selectedPaisa() { return this.selectedItems().reduce((s, i) => s + paisa(i.remaining), 0); },
                selectedTotal() { return this.selectedPaisa() / 100; },
                hasToday() { return this.items.some((i) => i.kind === 'current'); },
                setKeys(keys) { this.selected = this.ordered(keys); this.amount = (this.selectedPaisa() / 100).toFixed(2).replace(/\.00$/, ''); },
                selectDues() { this.setKeys(this.items.filter((i) => i.kind !== 'advance').map((i) => i.key)); },
                selectToday() { this.setKeys(this.items.filter((i) => i.kind === 'current').map((i) => i.key)); },
                clear() { this.selected = []; this.amount = ''; },
                /** Oldest first. Somiti takes whole days only; Kisti may end on a part-paid installment. */
                autoSelect() {
                    let left = paisa(this.amount);
                    const keys = [];
                    for (const i of this.items) {
                        if (left <= 0) break;
                        const r = paisa(i.remaining);
                        if (this.somiti && r > left) break;
                        keys.push(i.key);
                        left -= Math.min(r, left);
                    }
                    this.selected = keys;
                },
                partialNote() {
                    const items = this.selectedItems(), amt = paisa(this.amount), total = this.selectedPaisa();
                    if (!items.length || amt >= total) return '';
                    const last = items[items.length - 1];
                    const lastPart = paisa(last.remaining) - (total - amt);
                    return lastPart > 0 ? 'কিস্তি নং ' + bnDigits(last.sequence) + ' আংশিক: ' + bnMoney(lastPart / 100) : '';
                },
                problem() {
                    const amt = paisa(this.amount), total = this.selectedPaisa(), items = this.selectedItems();
                    if (!amt || !items.length) return '';
                    if (this.somiti && amt !== total) {
                        const leftover = amt - total;
                        return leftover > 0
                            ? bnMoney(leftover / 100) + ' বেশি — পুরো একটি দিনের টাকা হচ্ছে না। পরিমাণ ' + bnMoney(total / 100) + ' করুন অথবা আরেকটি দিন বাছুন।'
                            : 'নির্বাচিত দিনগুলোর জন্য ঠিক ' + bnMoney(total / 100) + ' নিতে হবে।';
                    }
                    if (!this.somiti) {
                        if (amt > total) return 'নির্বাচিত কিস্তির চেয়ে ' + bnMoney((amt - total) / 100) + ' বেশি। আরও কিস্তি বাছুন।';
                        const beforeLast = total - paisa(items[items.length - 1].remaining);
                        if (amt <= beforeLast) return 'টাকার তুলনায় বেশি কিস্তি বাছাই করা হয়েছে।';
                    }
                    return '';
                },
            };
        }
    </script>
    @endif
</x-layouts.app>
