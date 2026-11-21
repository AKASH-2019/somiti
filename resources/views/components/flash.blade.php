@if (session('success'))
    <div x-data="{ show: true }" x-show="show" role="status"
         class="no-print mb-5 flex items-start justify-between gap-4 rounded-md border border-ledger-200 bg-ledger-100 px-4 py-3 text-[15px] text-ledger-900">
        <span>{{ session('success') }}</span>
        <button type="button" class="text-ledger-700" @click="show = false" aria-label="বন্ধ করুন">✕</button>
    </div>
@endif
@if (session('error'))
    <div role="alert" class="no-print mb-5 rounded-md border border-danger/30 bg-danger-50 px-4 py-3 text-[15px] text-danger">
        {{ session('error') }}
    </div>
@endif
