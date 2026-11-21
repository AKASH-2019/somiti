<x-layouts.app title="নতুন কালেক্টর">
    <x-page-header title="নতুন কালেক্টর" description="কালেক্টর এই ইউজারনেম ও পাসওয়ার্ড দিয়ে লগইন করবেন।" />
    <form method="POST" action="{{ route('admin.collectors.store') }}" class="panel max-w-2xl space-y-4 p-5">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="name" label="কালেক্টরের নাম" required class="sm:col-span-2" />
            <x-field name="username" label="ইউজারনেম" required autocapitalize="none" autocomplete="off"
                     help="ইংরেজি ছোট অক্ষর ও সংখ্যা, যেমন: rahim" />
            <x-field name="mobile" label="মোবাইল নম্বর" required inputmode="tel" placeholder="০১৭XXXXXXXX" />
            <x-field name="password" label="পাসওয়ার্ড" type="password" required autocomplete="new-password" help="কমপক্ষে ৮ অক্ষর" />
            <x-field name="password_confirmation" label="পাসওয়ার্ড আবার লিখুন" type="password" required autocomplete="new-password" />
            <x-field name="opening_cash" label="হাতে থাকা প্রারম্ভিক টাকা (৳)" type="number" step="0.01" min="0" value="0"
                     help="কালেক্টরের কাছে আগে থেকে সংগৃহীত টাকা থাকলে লিখুন। পরে পরিবর্তন করা যাবে না।" class="sm:col-span-2" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('admin.collectors.index') }}" class="btn-quiet">বাতিল</a>
            <button type="submit" class="btn-primary">কালেক্টর তৈরি করুন</button>
        </div>
    </form>
</x-layouts.app>
