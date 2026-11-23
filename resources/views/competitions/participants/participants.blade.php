<x-layouts.markaz-layout>
    <div dir="rtl"
        class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6 mb-8">
        <div class="text-right w-full md:w-auto z-10">
            <h1 class="text-3xl font-black mb-2">المشاركون</h1>
            <p class="text-emerald-100/80 text-sm font-medium">{{ $competition->name }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
            <a href="{{ route('competitions.index', $competition) }}"
                class="px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white font-bold rounded-xl flex items-center gap-2 transition-all text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                رجوع للمسابقة
            </a>

            @can('export competition participants')
            <a href="{{ route('competitions.participants.export', array_merge(['competition' => $competition->id], request()->query())) }}"
                class="px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white font-bold rounded-xl flex items-center gap-2 transition-all text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H8a2 2 0 01-2-2V5a2 2 0 012-2h6l6 6v11a2 2 0 01-2 2z" />
                </svg>
                تصدير Excel
            </a>
            @endcan

            @can('create competition participants')
            <a href="{{ route('competitions.participants.create', $competition) }}"
                class="w-full md:w-auto px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-2xl flex items-center justify-center gap-2 transition-all shadow-lg hover:shadow-emerald-500/20 active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                إضافة مشارك
            </a>
            @endcan
        </div>

        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6">
        <form method="GET" class="flex flex-col lg:flex-row gap-4 items-end" dir="rtl">
            <div class="w-full lg:flex-1">
                <label for="filter_search" class="block text-xs font-bold text-gray-400 mb-1.5">البحث بالاسم</label>
                <input id="filter_search" type="search" name="search" value="{{ request('search') }}"
                    placeholder="ابحث باسم المشارك..."
                    class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-1 focus:ring-[#0a5c36] focus:border-[#0a5c36] transition-all bg-white text-right">
            </div>
            <div class="w-full lg:w-48">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">المستوى</label>
                <x-searchable-select
                    name="level_id"
                    :options="$levels->map(fn($competitionLevel) => ['value' => $competitionLevel->id, 'label' => $competitionLevel->level->name ?? '-'])->values()"
                    placeholder="كل المستويات"
                    searchPlaceholder="ابحث عن مستوى..."
                    :defaultValue="request('level_id', '')" />
            </div>
            <div class="w-full lg:w-48">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">المركز</label>
                <x-searchable-select
                    name="center_id"
                    :options="$centers->map(fn($center) => ['value' => $center->id, 'label' => $center->name])->values()"
                    placeholder="كل المراكز"
                    searchPlaceholder="ابحث عن مركز..."
                    :defaultValue="request('center_id', '')" />
            </div>
            <button type="submit"
                class="w-full lg:w-auto px-5 py-2.5 bg-[#0a5c36] hover:bg-[#08492a] text-white font-bold rounded-xl text-sm transition-all text-center">
                بحث
            </button>

            @if(request()->anyFilled(['search', 'level_id', 'center_id']))
            <a href="{{ route('competitions.participants', $competition) }}"
                class="w-full lg:w-auto px-5 py-2.5 bg-gray-50 hover:bg-gray-100 text-gray-500 hover:text-gray-700 font-bold border border-gray-200 rounded-xl text-sm transition-all text-center">
                مسح الفلاتر
            </a>
            @endif
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600">الاسم</th>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600">النوع</th>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600">المستوى</th>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600">المركز</th>
                        @canany(['edit competition participants', 'delete competition participants'])
                        <th class="px-6 py-4 text-sm font-bold text-gray-600 text-left">الإجراءات</th>
                        @endcanany
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($participants as $participant)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 font-bold text-gray-800">
                            {{ $participant->student->name ?? $participant->externalParticipant->name ?? '-' }}
                        </td>
                        <td class="px-6 py-4">
                            @if ($participant->student_id)
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-600">طالب</span>
                            @else
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600">خارجي</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-gray-600">{{ $participant->competitionLevel->level->name ?? '-' }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $participant->center->name ?? '-' }}</td>

                        @canany(['edit competition participants', 'delete competition participants'])
                        <td class="px-6 py-4 text-left">
                            <div class="flex items-center justify-end gap-3">
                                @can('edit competition participants')
                                <a href="{{ route('competitions.participants.edit', [$competition, $participant]) }}"
                                    class="text-emerald-500 hover:text-emerald-700 transition inline-block">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                                @endcan

                                @can('delete competition participants')
                                <form action="{{ route('competitions.participants.destroy', [$competition, $participant]) }}" method="POST"
                                    onsubmit="confirmDelete(event, { name: '{{ e($participant->student->name ?? $participant->externalParticipant->name ?? 'المشارك') }}', type: 'المشارك' })"
                                    class="text-red-400 hover:text-red-600 transition inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </td>
                        @endcanany
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">لا يوجد مشاركون حتى الآن.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($participants->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $participants->links() }}
        </div>
        @endif
    </div>
</x-layouts.markaz-layout>