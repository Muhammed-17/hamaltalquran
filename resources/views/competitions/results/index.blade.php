<x-layouts.markaz-layout>
    <x-slot name="title">المسابقات</x-slot>

    <nav class="text-xs text-gray-400 mb-4 flex items-center gap-1">
        <span>/</span>
        <span>المسابقات</span>
    </nav>

    <h1 class="text-2xl font-black text-gray-800 mb-6">المسابقات</h1>

    @if($competitions->isEmpty())
    <div class="bg-white rounded-2xl border border-gray-100 p-10 text-center text-gray-400">
        لا توجد مسابقات حالياً.
    </div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach($competitions as $competition)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col justify-between">
            <div>
                <h3 class="font-bold text-gray-800 text-lg mb-2">{{ $competition->name }}</h3>
                <p class="text-xs text-gray-500 mb-4">عدد المستويات: {{ $competition->competition_levels_count }}</p>
            </div>
            <a href="{{ route('competitions.overview.levels', $competition) }}"
                class="w-full text-center px-4 py-2.5 bg-[#0a5c36] hover:bg-[#0d7a48] text-white font-bold rounded-xl transition-all">
                دخول
            </a>
        </div>
        @endforeach
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 mt-6">
        <x-pagination :paginator="$competitions" />
    </div>
    @endif
</x-layouts.markaz-layout>