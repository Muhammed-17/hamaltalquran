@php
$statusMap = [
'draft' => ['مسودة', 'bg-gray-100 text-gray-600'],
'active' => ['نشطة', 'bg-green-100 text-green-700'],
'closed' => ['مغلقة', 'bg-red-100 text-red-700'],
];
@endphp

<x-layouts.markaz-layout>
    <div dir="rtl"
        class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6 mb-8">
        <div class="text-right w-full md:w-auto z-10">
            <h1 class="text-3xl font-black mb-2">إدارة المسابقات</h1>
            <p class="text-emerald-100/80 text-sm font-medium">
                @if(request()->anyFilled(['search', 'status']))
                {{ $competitions->total() }} نتيجة
                @else
                {{ $competitions->total() }} مسابقة مسجلة في النظام
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-4 w-full md:w-auto">
            @role('admin')
            <a href="{{ route('competitions.create') }}"
                class="w-full md:w-auto px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-2xl flex items-center justify-center gap-2 transition-all shadow-lg hover:shadow-emerald-500/20 active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                جديد
            </a>
            @endrole
        </div>

        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6">
        <form method="GET" action="{{ route('competitions.index') }}" class="flex flex-col lg:flex-row gap-4 items-end" dir="rtl">
            <div class="w-full lg:flex-1">
                <label for="filter_search" class="block text-xs font-bold text-gray-400 mb-1.5">البحث بالاسم</label>
                <input id="filter_search" type="search" name="search" value="{{ request('search') }}"
                    placeholder="ابحث باسم المسابقة..."
                    class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-1 focus:ring-[#0a5c36] focus:border-[#0a5c36] transition-all bg-white text-right">
            </div>

            <div class="w-full lg:w-48">
                <label for="filter_status" class="block text-xs font-bold text-gray-400 mb-1.5">الحالة</label>
                <select id="filter_status" name="status"
                    class="w-full p-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-1 focus:ring-[#0a5c36] focus:border-[#0a5c36] transition-all bg-white appearance-none">
                    <option value="">كل الحالات</option>
                    <option value="draft" @selected(request('status')==='draft' )>مسودة</option>
                    <option value="active" @selected(request('status')==='active' )>نشطة</option>
                    <option value="closed" @selected(request('status')==='closed' )>مغلقة</option>
                </select>
            </div>

            <button type="submit"
                class="w-full lg:w-auto px-5 py-2.5 bg-[#0a5c36] hover:bg-[#08492a] text-white font-bold rounded-xl text-sm transition-all text-center">
                بحث
            </button>

            @if(request()->anyFilled(['search', 'status']))
            <a href="{{ route('competitions.index') }}"
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
                        <th class="px-6 py-4 text-sm font-bold text-gray-600">اسم المسابقة</th>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600">الحالة</th>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600 text-center">المستويات</th>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600 text-center">المشاركون</th>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600 text-left">الإجراءات</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @forelse ($competitions as $competition)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 font-bold text-gray-800">{{ $competition->name }}</td>
                        <td class="px-6 py-4">
                            @php [$label, $classes] = $statusMap[$competition->status] ?? ['-', 'bg-gray-100 text-gray-600']; @endphp
                            <span class="px-3 py-1 rounded-full text-xs font-bold {{ $classes }}">{{ $label }}</span>
                        </td>
                        <td class="px-6 py-4 text-gray-600 text-center">{{ $competition->levels_count }}</td>
                        <td class="px-6 py-4 text-gray-600 text-center">{{ $competition->competition_participants_count }}</td>
                        <td class="px-6 py-4 text-left">
                            <div class="flex items-center justify-end gap-3 flex-wrap">
                                @can('view competitions')
                                <a href="{{ route('competitions.overview.levels', $competition) }}"
                                    class="text-[#0a5c36] hover:text-[#084d2d] transition" title="المستويات">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </a>
                                @endcan

                                @can('view competition examiners')
                                <a href="{{ route('competitions.examiners', $competition) }}"
                                    class="text-teal-600 hover:text-teal-800 transition" title="المختبرون">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </a>
                                @endcan

                                @can('view competition participants')
                                <a href="{{ route('competitions.participants', $competition) }}"
                                    class="text-indigo-500 hover:text-indigo-700 transition" title="المشاركون">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                                    </svg>
                                </a>
                                @endcan

                                @can('examine competition participants')
                                <a href="{{ route('competitions.overview.levels', $competition) }}"
                                    class="text-sky-500 hover:text-sky-700 transition" title="عرض الأختبار">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a>
                                @endcan

                                @role('admin')
                                <a href="{{ route('competitions.results', $competition) }}"
                                    class="text-amber-500 hover:text-amber-700 transition" title="النتائج">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                </a>
                                @endrole

                                @role('admin')
                                <a href="{{ route('competitions.edit', $competition) }}" class="text-blue-500 hover:text-blue-700 transition" title="تعديل المسابقة">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                                @endrole

                                @role('admin')
                                <form method="POST" action="{{ route('competitions.duplicate', $competition) }}" class="text-purple-500 hover:text-purple-700 transition">
                                    @csrf
                                    <button type="submit" title="نسخ المسابقة">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                </form>
                                @endrole

                                @role('admin')
                                <form method="POST" action="{{ route('competitions.destroy', $competition) }}"
                                    onsubmit="confirmDelete(event, { name: '{{ e($competition->name) }}', type: 'المسابقة' })"
                                    class="text-red-400 hover:text-red-600 transition">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="حذف المسابقة">
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
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            @if(request()->anyFilled(['search', 'status']))
                            لا توجد مسابقات تطابق الفلاتر المحددة.
                            @else
                            لا توجد مسابقات مسجلة حالياً.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$competitions" />
    </div>
</x-layouts.markaz-layout>