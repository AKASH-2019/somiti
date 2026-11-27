{{--
    Shared member fields.
    $member = existing member or null; $collectors = list for admin create (null otherwise);
    $suggestedCode = next free ID for a new member; $codeEditable = show the ID field; $withNotes = include notes.
--}}
@php
    $member ??= null;
    $codeEditable ??= true;
    $withNotes ??= true;
@endphp
<div class="grid gap-4 sm:grid-cols-2">
    @if ($codeEditable)
        <x-field name="member_code" label="সদস্য আইডি" :value="$member?->member_code ?? ($suggestedCode ?? '')"
                 :required="(bool) $member" class="uppercase" maxlength="20" autocomplete="off"
                 :help="$member ? 'আইডি বদলালে পুরনো রসিদেও নতুন আইডি দেখাবে।' : 'নিজে থেকে পরের নম্বর দেওয়া হয়েছে। চাইলে খাতার নম্বর লিখুন, যেমন: S5-010'" />
    @endif
    <x-field name="name" label="সদস্যের নাম" :value="$member?->name" required autocomplete="off" :class="$codeEditable ? '' : 'sm:col-span-2'" />
    <x-field name="business_name" label="প্রতিষ্ঠানের নাম" :value="$member?->business_name" />
    <x-field name="mobile" label="মোবাইল নম্বর" :value="$member?->mobile" required inputmode="tel" maxlength="16"
             placeholder="০১৭XXXXXXXX" help="বাংলা বা ইংরেজি — যেকোনো অঙ্কে লিখতে পারেন।" />
    <x-field name="business_address" label="প্রতিষ্ঠানের ঠিকানা" :value="$member?->business_address" class="sm:col-span-2" />
    <x-field name="joined_at" label="সমিতিতে যোগদানের তারিখ" type="date" :value="$member?->joined_at?->toDateString() ?? today()->toDateString()"
             :max="today()->toDateString()" required help="পুরনো সদস্য হলে আসল যোগদানের তারিখ দিন।" />

    @isset($collectors)
        <div>
            <label for="collector_id" class="field-label">দায়িত্বপ্রাপ্ত কালেক্টর<span class="text-danger" aria-hidden="true"> *</span></label>
            <select id="collector_id" name="collector_id" class="field" required>
                <option value="">— নির্বাচন করুন —</option>
                @foreach ($collectors as $c)
                    <option value="{{ $c->id }}" @selected(old('collector_id', request('collector')) == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
            @if ($collectors->isEmpty())
                <p class="field-help">কোনো সক্রিয় কালেক্টর নেই। আগে <a href="{{ route('admin.collectors.create') }}" class="font-semibold text-ledger-700 underline">কালেক্টর তৈরি করুন</a>।</p>
            @endif
            @error('collector_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    @endisset

    @if ($withNotes)
        <div class="sm:col-span-2">
            <label for="notes" class="field-label">মন্তব্য</label>
            <textarea id="notes" name="notes" rows="2" class="field">{{ old('notes', $member?->notes) }}</textarea>
            @error('notes') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    @endif
</div>
