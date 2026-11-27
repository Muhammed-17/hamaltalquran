<x-layouts.markaz-layout>
    <div dir="rtl" class="print:hidden">
        <div class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6 mb-8">
            <div class="text-right w-full md:w-auto z-10">
                <h1 class="text-3xl font-black mb-2">النتائج</h1>
                <p class="text-emerald-100/80 text-sm font-medium">{{ $competition->name }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <a href="{{ route('competitions.index', $competition) }}"
                    class="px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white font-bold rounded-xl flex items-center gap-2 transition-all text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    رجوع
                </a>
                <button onclick="window.print()"
                    class="px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white font-bold rounded-xl flex items-center gap-2 transition-all text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4H8v4a1 1 0 001 1zm-2-9h10V4H8v4z" />
                    </svg>
                    طباعة
                </button>
                @role('admin')
                <a href="{{ route('competitions.results.export', array_merge(['competition' => $competition->id], request()->query())) }}"
                    class="px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-2xl flex items-center gap-2 transition-all shadow-lg text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H8a2 2 0 01-2-2V5a2 2 0 012-2h6l6 6v11a2 2 0 01-2 2z" />
                    </svg>
                    تصدير Excel
                </a>
                @endrole
            </div>

            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6">
            <form method="GET" class="flex gap-3">
                <select name="level_id" onchange="this.form.submit()"
                    class="w-full sm:w-64 p-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-1 focus:ring-[#0a5c36] focus:border-[#0a5c36] transition-all bg-white appearance-none">
                    <option value="">كل المستويات</option>
                    @foreach ($levels as $competitionLevel)
                    <option value="{{ $competitionLevel->id }}" @selected(request('level_id')==$competitionLevel->id)>
                        {{ $competitionLevel->level->name ?? '-' }}
                    </option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600">الترتيب</th>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600">الاسم</th>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600">المستوى</th>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600">المجموع</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($results as $index => $result)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 text-gray-600">
                            @if ($index === 0)
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-amber-100 text-amber-700 text-xs font-black">1</span>
                            @else
                            {{ $index + 1 }}
                            @endif
                        </td>
                        <td class="px-6 py-4 font-bold text-gray-800">
                            {{ $result->student->name ?? $result->externalParticipant->name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-gray-600">{{ $result->competitionLevel->level->name ?? '-' }}</td>
                        <td class="px-6 py-4 font-black text-[#0a5c36]">{{ $result->competition_answers_sum_score ?? 0 }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500">لا توجد نتائج بعد.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.markaz-layout>