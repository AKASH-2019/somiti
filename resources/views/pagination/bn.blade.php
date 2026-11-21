@if ($paginator->hasPages())
    @php($link = 'inline-flex min-w-9 items-center justify-center rounded-md border px-3 py-1.5 text-[15px]')
    <nav class="no-print mt-4 flex flex-wrap items-center justify-between gap-3" aria-label="পাতা নির্বাচন">
        <p class="text-sm text-ink-soft">
            মোট {{ bn_digits($paginator->total()) }}টির মধ্যে {{ bn_digits($paginator->firstItem()) }}–{{ bn_digits($paginator->lastItem()) }}
        </p>
        <div class="flex flex-wrap gap-1">
            @if ($paginator->onFirstPage())
                <span class="{{ $link }} border-rule text-ink-soft/50">আগের</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }} border-rule bg-white hover:bg-ledger-50">আগের</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="{{ $link }} border-transparent text-ink-soft">…</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="{{ $link }} border-ledger-700 bg-ledger-700 font-semibold text-white">{{ bn_digits($page) }}</span>
                        @else
                            <a href="{{ $url }}" class="{{ $link }} border-rule bg-white hover:bg-ledger-50">{{ bn_digits($page) }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }} border-rule bg-white hover:bg-ledger-50">পরের</a>
            @else
                <span class="{{ $link }} border-rule text-ink-soft/50">পরের</span>
            @endif
        </div>
    </nav>
@endif
