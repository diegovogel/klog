@if ($paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <div class="pagination__summary">
            @if ($paginator->firstItem())
                {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
            @endif
        </div>

        <div class="pagination__links">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="pagination__item pagination__item--disabled" aria-disabled="true">&lsaquo;</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="pagination__item" rel="prev" aria-label="{{ __('pagination.previous') }}">&lsaquo;</a>
            @endif

            {{-- Page Numbers --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagination__item pagination__item--dots">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pagination__item pagination__item--active" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="pagination__item" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="pagination__item" rel="next" aria-label="{{ __('pagination.next') }}">&rsaquo;</a>
            @else
                <span class="pagination__item pagination__item--disabled" aria-disabled="true">&rsaquo;</span>
            @endif
        </div>
    </nav>
@endif
