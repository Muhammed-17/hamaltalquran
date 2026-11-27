<x-layouts.markaz-layout>
    <div dir="rtl"
        class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6 mb-8">
        <div class="text-right w-full md:w-auto z-10">
            <h1 class="text-3xl font-black mb-2">مستويات المسابقة</h1>
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
            @role('admin')
            <a href="{{ route('competitions.levels.select', $competition) }}"
                class="w-full md:w-auto px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-2xl flex items-center justify-center gap-2 transition-all shadow-lg hover:shadow-emerald-500/20 active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                إضافة مستوى
            </a>
            @endrole
        </div>

        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
    </div>

    @if (session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 px-5 py-4 rounded-2xl text-sm font-bold flex items-start gap-3 mb-6">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600">المستوى</th>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600">الأسئلة</th>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600">المشاركون</th>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600">المختبرون</th>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600 text-left">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($competitionLevels as $competitionLevel)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <p class="font-bold text-gray-800">{{ $competitionLevel->level->name ?? '-' }}</p>
                            @if ($competitionLevel->level?->memorization_part)
                            <p class="text-xs text-gray-500">{{ $competitionLevel->level->memorization_part }}</p>
                            @elseif ($competitionLevel->level?->memorization_from_part || $competitionLevel->level?->memorization_to_part)
                            <p class="text-xs text-gray-500">
                                من {{ $competitionLevel->level->memorization_from_part ?? '...' }}
                                إلى {{ $competitionLevel->level->memorization_to_part ?? '...' }}
                            </p>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-gray-600">{{ $competitionLevel->competition_questions_count }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $competitionLevel->competition_participants_count }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $competitionLevel->competition_examiner_levels_count }}</td>
                        <td class="px-6 py-4 text-left">
                            <div class="flex items-center justify-end gap-3">
                                @can('view level questions')
                                <a href="{{ route('competitions.level-questions', [$competition, 'level_id' => $competitionLevel->id]) }}"
                                    class="text-emerald-600 hover:text-emerald-800 transition" title="أسئلة المستوى">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </a>
                                @endcan
                                @role('admin')
                                <form action="{{ route('competitions.levels.destroy', [$competition, $competitionLevel]) }}" method="POST"
                                    onsubmit="confirmDelete(event, { name: '{{ e($competitionLevel->level->name ?? 'المستوى') }}', type: 'المستوى' })"
                                    class="text-red-400 hover:text-red-600 transition">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                                @endrole
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">لا توجد مستويات مضافة لهذه المسابقة بعد.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.markaz-layout>