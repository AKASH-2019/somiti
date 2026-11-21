<x-layouts.app title="নতুন কিস্তি অ্যাকাউন্ট">
    <x-page-header title="নতুন কিস্তি অ্যাকাউন্ট" description="ঋণের শর্ত একবার সংরক্ষণের পর পরিবর্তন করা যায় না — সাবধানে মিলিয়ে নিন।" />

    @if ($members->isEmpty())
        <div class="panel max-w-3xl p-5 text-ink-soft">
            ঋণ দেওয়ার মতো কোনো সক্রিয় সদস্য নেই।
            <a href="{{ route(auth()->user()->isAdmin() ? 'admin.members.create' : 'collector.members.create') }}" class="font-semibold text-ledger-700 underline">আগে সদস্য যোগ করুন</a>।
        </div>
    @else
        <form method="POST" action="{{ route($rp . '.store') }}" class="grid max-w-5xl gap-6 lg:grid-cols-5"
              x-data="kistiForm({
                  frequency: @js(old('frequency', 'daily')),
                  loan: @js(old('loan_amount', '')),
                  charge: @js(old('service_charge', '')),
                  each: @js(old('installment_amount', '')),
                  start: @js(old('start_date', today()->toDateString())),
                  first: @js(old('first_installment_date', '')),
              })">
            @csrf
            <div class="panel space-y-4 p-5 lg:col-span-3">
                <div>
                    <label for="member_id" class="field-label">সদস্য<span class="text-danger" aria-hidden="true"> *</span></label>
                    <select id="member_id" name="member_id" class="field" required>
                        <option value="">— সদস্য নির্বাচন করুন —</option>
                        @foreach ($members as $m)
                            <option value="{{ $m->id }}" @selected(old('member_id', request('member')) == $m->id)>
                                {{ $m->member_code }} — {{ $m->name }}{{ $m->business_name ? ' (' . $m->business_name . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('member_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <fieldset>
                    <legend class="field-label">কিস্তির ধরন</legend>
                    <div class="flex gap-2">
                        @foreach (\App\Enums\KistiFrequency::cases() as $f)
                            <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-md border border-rule px-3 py-2 text-[15px] has-[:checked]:border-ledger-600 has-[:checked]:bg-ledger-100 has-[:checked]:font-semibold">
                                <input type="radio" name="frequency" value="{{ $f->value }}" x-model="frequency" @change="suggestFirst()" class="text-ledger-700">
                                {{ $f->label() }} কিস্তি
                            </label>
                        @endforeach
                    </div>
                    @error('frequency') <p class="field-error">{{ $message }}</p> @enderror
                </fieldset>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="loan_amount" class="field-label">ঋণের পরিমাণ (৳)<span class="text-danger" aria-hidden="true"> *</span></label>
                        <input id="loan_amount" name="loan_amount" type="number" step="0.01" min="1" class="field" x-model="loan" required>
                        @error('loan_amount') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="service_charge" class="field-label">সার্ভিস চার্জ (৳)</label>
                        <input id="service_charge" name="service_charge" type="number" step="0.01" min="0" class="field" x-model="charge">
                        <p class="field-help" x-show="rate() !== null" x-text="'ঋণের ' + bnDigits(rate()) + '%'"></p>
                        @error('service_charge') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="installment_amount" class="field-label">প্রতি কিস্তির টাকা (৳)<span class="text-danger" aria-hidden="true"> *</span></label>
                        <input id="installment_amount" name="installment_amount" type="number" step="0.01" min="1" class="field" x-model="each" required>
                        @error('installment_amount') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="start_date" class="field-label">কিস্তি শুরুর তারিখ<span class="text-danger" aria-hidden="true"> *</span></label>
                        <input id="start_date" name="start_date" type="date" class="field" x-model="start" @change="suggestFirst()" required>
                        <p class="field-help">যেদিন ঋণ দেওয়া হলো</p>
                        @error('start_date') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="first_installment_date" class="field-label">প্রথম কিস্তির তারিখ<span class="text-danger" aria-hidden="true"> *</span></label>
                        <input id="first_installment_date" name="first_installment_date" type="date" class="field" x-model="first" :min="start" required>
                        <p class="field-help">ছুটির দিন পড়লে পরের কার্যদিবসে যাবে</p>
                        @error('first_installment_date') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="notes" class="field-label">মন্তব্য</label>
                    <textarea id="notes" name="notes" rows="2" class="field">{{ old('notes') }}</textarea>
                </div>
            </div>

            <aside class="panel h-fit p-5 lg:col-span-2 lg:sticky lg:top-20">
                <h2 class="mb-3 text-lg font-bold text-ledger-900">হিসাবের সারাংশ</h2>
                <dl class="space-y-2 text-[15px]">
                    <div class="flex justify-between"><dt class="text-ink-soft">ঋণের পরিমাণ</dt><dd x-text="bnMoney(n(loan))"></dd></div>
                    <div class="flex justify-between"><dt class="text-ink-soft">সার্ভিস চার্জ</dt><dd x-text="bnMoney(n(charge))"></dd></div>
                    <div class="flex justify-between border-t border-rule pt-2 font-semibold"><dt>মোট পরিশোধযোগ্য</dt><dd x-text="bnMoney(total())"></dd></div>
                    <div class="flex justify-between"><dt class="text-ink-soft" x-text="frequency === 'daily' ? 'দৈনিক কিস্তি' : 'সাপ্তাহিক কিস্তি'"></dt><dd x-text="bnMoney(n(each))"></dd></div>
                    <div class="flex justify-between text-lg font-bold text-ledger-900"><dt>মোট কিস্তি</dt><dd x-text="count() ? bnDigits(count()) + 'টি' : '—'"></dd></div>
                    <template x-if="count() && lastAmount() !== n(each)">
                        <div class="rounded-md bg-pending-50 px-3 py-2 text-sm text-pending" x-text="'শেষ কিস্তি: ' + bnMoney(lastAmount())"></div>
                    </template>
                </dl>
                <button type="submit" class="btn-primary mt-5 w-full">কিস্তি অ্যাকাউন্ট খুলুন</button>
                <a href="{{ route($rp . '.index') }}" class="btn-quiet mt-2 w-full">বাতিল</a>
            </aside>
        </form>

        <script>
            function kistiForm(init) {
                return {
                    ...init,
                    n(v) { const x = parseFloat(v); return isNaN(x) ? 0 : Math.round(x * 100) / 100; },
                    paisa(v) { return Math.round(this.n(v) * 100); },
                    total() { return (this.paisa(this.loan) + this.paisa(this.charge)) / 100; },
                    count() {
                        const t = this.paisa(this.loan) + this.paisa(this.charge), e = this.paisa(this.each);
                        return e > 0 && t > 0 && e <= t ? Math.ceil(t / e) : 0;
                    },
                    lastAmount() {
                        const t = this.paisa(this.loan) + this.paisa(this.charge), e = this.paisa(this.each), c = this.count();
                        return c ? (t - e * (c - 1)) / 100 : 0;
                    },
                    rate() {
                        const l = this.n(this.loan), c = this.n(this.charge);
                        return l > 0 && c > 0 ? Math.round((c / l) * 1000) / 10 : null;
                    },
                    suggestFirst() {
                        if (!this.start) return;
                        const d = new Date(this.start + 'T00:00:00');
                        d.setDate(d.getDate() + (this.frequency === 'weekly' ? 7 : 1));
                        const pad = (x) => String(x).padStart(2, '0');
                        this.first = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
                    },
                    init() { if (!this.first) this.suggestFirst(); },
                };
            }
        </script>
    @endif
</x-layouts.app>
