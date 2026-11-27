{{-- "Open a Somiti account now" section of the new-member form. $shareValue --}}
@php($openOld = session()->hasOldInput() ? (bool) old('open_somiti') : true)
<section class="panel mt-6 p-5"
         x-data="{ open: @js($openOld), shares: {{ (int) old('number_of_shares', 1) }}, value: {{ (float) $shareValue }} }">
    <label class="flex items-start gap-3">
        <input type="checkbox" name="open_somiti" value="1" x-model="open" class="mt-1 h-5 w-5 rounded border-rule text-ledger-700">
        <span>
            <span class="text-lg font-bold text-ledger-900">সমিতি অ্যাকাউন্টও খুলুন</span>
            <span class="block text-sm text-ink-soft">শুধু কিস্তির সদস্য হলে টিক তুলে দিন।</span>
        </span>
    </label>

    <fieldset x-show="open" x-cloak :disabled="!open" class="mt-5 space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="number_of_shares" class="field-label">শেয়ারের সংখ্যা<span class="text-danger" aria-hidden="true"> *</span></label>
                <input id="number_of_shares" name="number_of_shares" type="number" min="1" max="1000" class="field" x-model.number="shares" required>
                @error('number_of_shares') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="rounded-md bg-ledger-50 px-4 py-3">
                <p class="text-sm text-ink-soft">দৈনিক জমার পরিমাণ</p>
                <p class="text-2xl font-bold text-ledger-900" x-text="bnMoney((shares || 0) * value)"></p>
                <p class="text-sm text-ink-soft"><span x-text="bnDigits(shares || 0)"></span> × <span x-text="bnMoney(value)"></span></p>
            </div>
            <x-field name="somiti_start_date" label="সঞ্চয় শুরুর তারিখ" type="date" :value="today()->toDateString()" required
                     help="আগের তারিখ দিলে সেই দিনগুলো বকেয়া হিসেবে আসবে এবং টাকা সংগ্রহ পাতা থেকে জমা নেওয়া যাবে।" />
        </div>
        <div class="rounded-md border border-rule p-4">
            <p class="font-semibold text-ledger-900">প্রারম্ভিক সঞ্চয় (পুরনো সদস্যের জন্য)</p>
            <p class="field-help mb-3">কাগজের খাতায় এ পর্যন্ত জমা থাকলে মোট টাকা লিখুন। নতুন সদস্যের জন্য খালি রাখুন।</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="opening_balance" label="প্রারম্ভিক সঞ্চয় (৳)" type="number" step="0.01" min="0" inputmode="decimal" placeholder="০" />
                <x-field name="opening_balance_note" label="বিবরণ" placeholder="যেমন: পুরনো খাতা, জানুয়ারি–অক্টোবর" />
            </div>
        </div>
    </fieldset>
</section>
