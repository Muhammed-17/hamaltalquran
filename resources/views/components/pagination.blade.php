@props(['paginator'])

@if($paginator->hasPages())
<div class="flex flex-col sm:flex-row items-center justify-between gap-4 px-6 py-4 border-t border-gray-100">

    {{-- ملخص النتائج --}}
    <div class="text-xs text-gray-400 font-medium">
        الصفحة <span class="font-bold text-gray-700">{{ $paginator->currentPage() }}</span>
        من أصل <span class="font-bold text-gray-700">{{ $paginator->lastPage() }}</span> صفحة
        — العروض الحالية
        <span class="font-mono text-gray-700">{{ $paginator->firstItem() }}</span>
        إلى
        <span class="font-mono text-gray-700">{{ $paginator->lastItem() }}</span>
        من <span class="font-mono text-gray-700">{{ $paginator->total() }}</span>
    </div>

    {{-- أزرار الترقيم --}}
    <div class="flex items-center gap-1" dir="ltr">

        {{-- التالي --}}
        @if($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}"
            class="px-2.5 py-1.5 rounded-lg border text-xs font-bold text-gray-600 border-gray-200 hover:bg-gray-50 active:scale-95 transition-all">
            ‹ التالي
        </a>
        @else
        <span class="px-2.5 py-1.5 rounded-lg border text-xs font-bold text-gray-300 border-gray-100 cursor-not-allowed">
            ‹ التالي
        </span>
        @endif

        {{-- أرقام الصفحات (مع نافذة ذكية وعلامات "...") --}}
        @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $delta = 2;

        $rangeStart = max(2, $current - $delta);
        $rangeEnd = min($last - 1, $current + $delta);

        $pagesToShow = collect([1]);
        if ($rangeStart > 2) $pagesToShow->push('...');
        for ($p = $rangeStart; $p <= $rangeEnd; $p++) $pagesToShow->push($p);
            if ($rangeEnd < $last - 1) $pagesToShow->push('...');
                if ($last > 1) $pagesToShow->push($last);
                @endphp

                @foreach($pagesToShow as $page)
                @if($page === '...')
                <span class="px-1.5 text-gray-400 select-none text-xs">...</span>
                @elseif($page == $current)
                <span class="px-3 py-1.5 rounded-lg text-xs font-bold min-w-[32px] text-center bg-[#0a5c36] text-white shadow-sm">
                    {{ $page }}
                </span>
                @else
                <a href="{{ $paginator->url($page) }}"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold min-w-[32px] text-center text-gray-600 border border-gray-200 hover:bg-gray-50 transition-all">
                    {{ $page }}
                </a>
                @endif
                @endforeach

                {{-- السابق --}}
                @if($paginator->onFirstPage())
                <span class="px-2.5 py-1.5 rounded-lg border text-xs font-bold text-gray-300 border-gray-100 cursor-not-allowed">
                    السابق ›
                </span>
                @else
                <a href="{{ $paginator->previousPageUrl() }}"
                    class="px-2.5 py-1.5 rounded-lg border text-xs font-bold text-gray-600 border-gray-200 hover:bg-gray-50 active:scale-95 transition-all">
                    السابق ›
                </a>
                @endif

    </div>
</div>
@endif