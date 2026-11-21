<x-layouts.app title="রিপোর্ট">
    <x-page-header title="রিপোর্ট" description="প্রতিটি রিপোর্ট পর্দায় দেখা, প্রিন্ট, PDF ও Excel আকারে নামানো যায়।" />

    <div class="space-y-8">
        @foreach ($groups as $group => $reports)
            <section>
                <h2 class="mb-3 text-lg font-bold text-ledger-900">{{ $group }}</h2>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($reports as $r)
                        <a href="{{ route($rp . '.show', $r->key()) }}" class="panel block p-4 transition-colors hover:border-ledger-600 hover:bg-ledger-50">
                            <p class="text-[17px] font-semibold text-ink">{{ $r->title() }}</p>
                            <p class="mt-1 text-sm text-ink-soft">{{ $r->description() }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.app>
