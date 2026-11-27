@php($days = \App\Support\BanglaFormatter::DAYS)
<x-layouts.app title="সেটিংস">
    <x-page-header title="সেটিংস" description="প্রতিষ্ঠানের তথ্য, শেয়ারের মূল্য ও সাপ্তাহিক ছুটি" />

    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-3xl space-y-6"
          x-data="{ share: {{ (float) old('share_value', $settings['share_value']) }}, original: {{ (float) $settings['share_value'] }} }">
        @csrf
        @method('PUT')

        <section class="panel p-5">
            <h2 class="mb-4 text-lg font-bold text-ledger-900">প্রতিষ্ঠানের তথ্য</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="org_name" class="field-label">প্রতিষ্ঠানের নাম</label>
                    <input id="org_name" name="org_name" class="field" value="{{ old('org_name', $settings['org_name']) }}" required>
                    @error('org_name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="org_address" class="field-label">ঠিকানা</label>
                    <input id="org_address" name="org_address" class="field" value="{{ old('org_address', $settings['org_address']) }}">
                    @error('org_address') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="member_id_prefix" class="field-label">সদস্য আইডির শুরু</label>
                    <input id="member_id_prefix" name="member_id_prefix" class="field uppercase" maxlength="10" value="{{ old('member_id_prefix', $settings['member_id_prefix']) }}" required>
                    <p class="field-help">নতুন সদস্যের আইডি হবে: {{ old('member_id_prefix', $settings['member_id_prefix']) }}-০০১, -০০২ …</p>
                    @error('member_id_prefix') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="org_mobile" class="field-label">মোবাইল নম্বর</label>
                    <input id="org_mobile" name="org_mobile" class="field" inputmode="tel" value="{{ old('org_mobile', $settings['org_mobile']) }}">
                    @error('org_mobile') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="panel p-5">
            <h2 class="mb-4 text-lg font-bold text-ledger-900">সমিতির শেয়ার</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="share_value" class="field-label">প্রতি শেয়ারের দৈনিক মূল্য (৳)</label>
                    <input id="share_value" name="share_value" type="number" step="0.01" min="1" class="field" x-model.number="share" required>
                    @error('share_value') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="rounded-md bg-ledger-50 px-4 py-3 text-[15px]">
                    <p class="font-semibold text-ledger-900">উদাহরণ</p>
                    <template x-for="n in [1, 5, 10]" :key="n">
                        <p><span x-text="bnDigits(n)"></span> শেয়ার = <span x-text="bnMoney(n * (share || 0))"></span> প্রতিদিন</p>
                    </template>
                </div>
            </div>
            <p class="field-help mt-3">নতুন মূল্য শুধু এখন থেকে খোলা সমিতি অ্যাকাউন্টে প্রযোজ্য হবে। বিদ্যমান অ্যাকাউন্টের দৈনিক জমা অপরিবর্তিত থাকবে।</p>
            <p x-show="share != original" x-cloak class="mt-2 rounded-md bg-pending-50 px-3 py-2 text-sm font-medium text-pending">শেয়ারের মূল্য পরিবর্তন হচ্ছে। সংরক্ষণের আগে নিশ্চিত হয়ে নিন।</p>
        </section>

        <section class="panel p-5">
            <h2 class="mb-1 text-lg font-bold text-ledger-900">সাপ্তাহিক ছুটি</h2>
            <p class="field-help mb-4">নির্বাচিত দিনগুলোতে দৈনিক সমিতি জমা ও দৈনিক কিস্তি নির্ধারিত হবে না। নির্দিষ্ট তারিখের ছুটি <a href="{{ route('admin.holidays.index') }}" class="font-semibold text-ledger-700 underline">ছুটির দিন</a> পাতায় যোগ করুন।</p>
            @php($selected = array_map('intval', (array) old('weekly_off_days', $settings['weekly_off_days'])))
            <div class="flex flex-wrap gap-2">
                @foreach ($days as $index => $day)
                    <label class="flex cursor-pointer items-center gap-2 rounded-md border border-rule px-3 py-2 text-[15px] has-[:checked]:border-ledger-600 has-[:checked]:bg-ledger-100">
                        <input type="checkbox" name="weekly_off_days[]" value="{{ $index }}" class="h-4 w-4 rounded border-rule text-ledger-700" @checked(in_array($index, $selected, true))>
                        {{ $day }}
                    </label>
                @endforeach
            </div>
            @error('weekly_off_days') <p class="field-error">{{ $message }}</p> @enderror
        </section>

        <section class="panel p-5">
            <h2 class="mb-4 text-lg font-bold text-ledger-900">রসিদ</h2>
            <label for="receipt_footer" class="field-label">রসিদের নিচের লেখা</label>
            <input id="receipt_footer" name="receipt_footer" class="field" value="{{ old('receipt_footer', $settings['receipt_footer']) }}">
            @error('receipt_footer') <p class="field-error">{{ $message }}</p> @enderror
        </section>

        <div class="flex justify-end">
            <button type="submit" class="btn-primary">সংরক্ষণ করুন</button>
        </div>
    </form>
</x-layouts.app>
