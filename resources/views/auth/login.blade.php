<x-layouts.guest title="লগইন">
    <div class="mb-6 text-center text-white">
        <p class="text-2xl font-bold">{{ setting('org_name') }}</p>
        <p class="mt-1 text-ledger-200">সমিতি ও কিস্তি হিসাব</p>
    </div>

    <div class="rounded-lg bg-white p-6 shadow-lg sm:p-8">
        <h1 class="mb-5 border-b-4 border-double border-rule pb-2 text-xl font-bold text-ledger-900">আপনার অ্যাকাউন্টে প্রবেশ করুন</h1>

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4" x-data="{ show: false }">
            @csrf
            <div>
                <label for="username" class="field-label">ইউজারনেম</label>
                <input id="username" name="username" type="text" value="{{ old('username') }}" class="field" autocomplete="username" autocapitalize="none" required autofocus>
                @error('username') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="field-label">পাসওয়ার্ড</label>
                <div class="relative">
                    <input id="password" name="password" :type="show ? 'text' : 'password'" type="password" class="field pr-20" autocomplete="current-password" required>
                    <button type="button" class="absolute inset-y-0 right-0 px-3 text-sm font-semibold text-ledger-700" @click="show = !show" x-text="show ? 'লুকান' : 'দেখুন'">দেখুন</button>
                </div>
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-[15px]">
                <input type="checkbox" name="remember" class="h-4 w-4 rounded border-rule text-ledger-700 focus:ring-ledger-200">
                এই ডিভাইসে লগইন রাখুন
            </label>

            <button type="submit" class="btn-primary w-full py-2.5">লগইন করুন</button>
        </form>
    </div>

    <p class="mt-6 text-center text-sm text-ledger-200">পাসওয়ার্ড ভুলে গেলে এডমিনের সাথে যোগাযোগ করুন।</p>
</x-layouts.guest>
