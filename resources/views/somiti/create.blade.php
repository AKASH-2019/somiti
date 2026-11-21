<x-layouts.app title="নতুন সমিতি অ্যাকাউন্ট">
    <x-page-header title="নতুন সমিতি অ্যাকাউন্ট" :description="'বর্তমান শেয়ারের মূল্য: প্রতি শেয়ার ' . bn_money($shareValue) . ' প্রতিদিন'" />

    @if ($members->isEmpty())
        <div class="panel max-w-2xl p-5 text-ink-soft">
            অ্যাকাউন্ট খোলার মতো কোনো সক্রিয় সদস্য নেই।
            <a href="{{ route(auth()->user()->isAdmin() ? 'admin.members.create' : 'collector.members.create') }}" class="font-semibold text-ledger-700 underline">আগে সদস্য যোগ করুন</a>।
        </div>
    @else
        <form method="POST" action="{{ route($rp . '.store') }}" class="panel max-w-2xl space-y-4 p-5"
              x-data="{ shares: {{ (int) old('number_of_shares', 1) }}, value: {{ (float) $shareValue }} }">
            @csrf
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

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="number_of_shares" class="field-label">শেয়ারের সংখ্যা<span class="text-danger" aria-hidden="true"> *</span></label>
                    <input id="number_of_shares" name="number_of_shares" type="number" min="1" max="1000" step="1" class="field" x-model.number="shares" required>
                    @error('number_of_shares') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="rounded-md bg-ledger-50 px-4 py-3">
                    <p class="text-sm text-ink-soft">দৈনিক জমার পরিমাণ</p>
                    <p class="text-2xl font-bold text-ledger-900" x-text="bnMoney((shares || 0) * value)"></p>
                    <p class="text-sm text-ink-soft"><span x-text="bnDigits(shares || 0)"></span> × <span x-text="bnMoney(value)"></span></p>
                </div>
            </div>

            <x-field name="start_date" label="সঞ্চয় শুরুর তারিখ" type="date" :value="today()->toDateString()" required
                     help="এই তারিখ থেকে প্রতি কার্যদিবসে দৈনিক জমা নির্ধারিত হবে (ছুটির দিন বাদে)। আগের তারিখ দিলে সেই দিনগুলো বকেয়া হিসেবে আসবে, পরে টাকা সংগ্রহ পাতা থেকে জমা করা যাবে।" />

            <fieldset class="rounded-md border border-rule p-4">
                <legend class="px-1 text-[15px] font-semibold text-ledger-900">প্রারম্ভিক সঞ্চয় (পুরনো সদস্যের জন্য)</legend>
                <p class="field-help mb-3">সদস্য আগে থেকেই কাগজের খাতায় সঞ্চয় করে থাকলে, এখন পর্যন্ত মোট জমা টাকা এখানে লিখুন। এটি সদস্যের মোট সঞ্চয়ে যোগ হবে, কিন্তু কোনো কালেক্টরের হাতের টাকা হিসেবে গণ্য হবে না। নতুন সদস্যের জন্য খালি রাখুন।</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="opening_balance" label="প্রারম্ভিক সঞ্চয় (৳)" type="number" step="0.01" min="0" placeholder="০" inputmode="decimal" />
                    <x-field name="opening_balance_note" label="বিবরণ" placeholder="যেমন: পুরনো খাতা, জানুয়ারি–অক্টোবর ২০২৬" />
                </div>
            </fieldset>

            <div>
                <label for="notes" class="field-label">মন্তব্য</label>
                <textarea id="notes" name="notes" rows="2" class="field">{{ old('notes') }}</textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route($rp . '.index') }}" class="btn-quiet">বাতিল</a>
                <button type="submit" class="btn-primary">অ্যাকাউন্ট খুলুন</button>
            </div>
        </form>
    @endif
</x-layouts.app>
