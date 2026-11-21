<x-layouts.app title="নতুন টাকা হস্তান্তর">
    <x-page-header title="টাকা হস্তান্তর" description="প্রাপক গ্রহণ নিশ্চিত করলে তবেই আপনার ক্যাশ থেকে টাকা বাদ যাবে।" />

    <form method="POST" action="{{ route($rp . '.store') }}" class="panel max-w-xl space-y-4 p-5"
          x-data="{ amount: @js(old('amount', '')), available: {{ (float) $available }} }">
        @csrf
        <div class="rounded-md bg-ledger-50 px-4 py-3 text-[15px]">
            হাতে আছে <span class="font-semibold">{{ bn_money($balance) }}</span> ·
            হস্তান্তরযোগ্য <span class="font-semibold">{{ bn_money($available) }}</span>
        </div>

        <div>
            <label for="receiver_id" class="field-label">যাকে টাকা প্রদান করবেন<span class="text-danger" aria-hidden="true"> *</span></label>
            <select id="receiver_id" name="receiver_id" class="field" required>
                <option value="">— নির্বাচন করুন —</option>
                @foreach ($receivers as $r)
                    <option value="{{ $r->id }}" @selected(old('receiver_id') == $r->id)>{{ $r->name }}{{ $r->isAdmin() ? ' (এডমিন)' : '' }}</option>
                @endforeach
            </select>
            @error('receiver_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="amount" class="field-label">হস্তান্তরের পরিমাণ (৳)<span class="text-danger" aria-hidden="true"> *</span></label>
            <input id="amount" name="amount" type="number" step="0.01" min="0.01" inputmode="decimal" class="field text-xl font-semibold" x-model="amount" required>
            <div class="mt-1 flex justify-between gap-2">
                <p class="text-sm text-danger" x-show="parseFloat(amount) > available" x-cloak>হস্তান্তরযোগ্য টাকার চেয়ে বেশি।</p>
                <button type="button" class="ml-auto text-sm font-semibold text-ledger-700 hover:underline" @click="amount = available">সব টাকা</button>
            </div>
            @error('amount') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <x-field name="handover_datetime" label="হস্তান্তরের তারিখ ও সময়" type="datetime-local" required
                 :value="now()->format('Y-m-d\TH:i')" :max="now()->format('Y-m-d\TH:i')" />

        <div>
            <label for="notes" class="field-label">মন্তব্য</label>
            <textarea id="notes" name="notes" rows="2" class="field">{{ old('notes') }}</textarea>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route($rp . '.index') }}" class="btn-quiet">বাতিল</a>
            <button type="submit" class="btn-primary" :disabled="!(parseFloat(amount) > 0) || parseFloat(amount) > available">টাকা হস্তান্তরের অনুরোধ পাঠান</button>
        </div>
    </form>
</x-layouts.app>
