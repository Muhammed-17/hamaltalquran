<x-layouts.markaz-layout>
    <div class="max-w-6xl mx-auto space-y-6">

        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-gray-800">الطلاب المطلوب إعادة اختبارهم</h2>
            <a href="{{ route('surah-tests.index.individual') }}"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm hover:bg-gray-50 transition-colors">
                رجوع لاختبارات السور
            </a>
        </div>

        <!-- ═══════════════════════════════════════ -->
        <!-- فلاتر -->
        <!-- ═══════════════════════════════════════ -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <form method="GET" action="{{ route('surah-tests.repeat-students') }}"
                class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اسم الطالب</label>
                    <input type="text" name="student_name" value="{{ request('student_name') }}"
                        placeholder="ابحث باسم الطالب..."
                        class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm px-4 py-2.5">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اسم الحلقة</label>
                    <x-searchable-select
                        name="circle_id"
                        :options="$circleOptions"
                        :defaultValue="request('circle_id', '')"
                        placeholder="كل الحلقات"
                        searchPlaceholder="ابحث باسم الحلقة..." />
                </div>
                @if(auth()->user()->hasAnyRole(['admin', 'general_manager']))
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اسم الفرع</label>
                    <x-searchable-select
                        name="center_id"
                        :options="$centerOptions"
                        :defaultValue="request('center_id', '')"
                        placeholder="كل الفروع"
                        searchPlaceholder="ابحث باسم الفرع..." />
                </div>
                @endif
                <div class="flex gap-2">
                    <button type="submit"
                        class="px-4 py-2 rounded-lg bg-[#0a5c36] text-white text-sm font-semibold hover:bg-[#0d7a48] transition-colors">
                        تصفية
                    </button>
                    <a href="{{ route('surah-tests.repeat-students') }}"
                        class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm hover:bg-gray-50 transition-colors">
                        إعادة تعيين
                    </a>
                </div>
            </form>
        </div>

        <!-- ═══════════════════════════════════════ -->
        <!-- جدول الطلاب -->
        <!-- ═══════════════════════════════════════ -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    @php
                        $currentSort = request('sort', 'test_date');
                        $currentDir  = request('dir', 'desc');
                        $sortLink = fn($field) => route('surah-tests.repeat-students') . '?' . http_build_query(
                            array_merge(request()->except(['sort', 'dir', 'page']), [
                                'sort' => $field,
                                'dir'  => ($currentSort === $field && $currentDir === 'asc') ? 'desc' : 'asc',
                            ])
                        );
                        $sortIcon = fn($field) => $currentSort !== $field
                            ? '<i class="fas fa-sort text-red-200 text-[10px] mr-1"></i>'
                            : ($currentDir === 'asc'
                                ? '<i class="fas fa-sort-up text-red-700 text-[10px] mr-1"></i>'
                                : '<i class="fas fa-sort-down text-red-700 text-[10px] mr-1"></i>');
                    @endphp
                    <thead class="bg-red-50 text-red-700">
                        <tr>
                            <th class="px-4 py-3 text-right font-semibold">
                                <a href="{{ $sortLink('student') }}" class="inline-flex items-center hover:text-red-900">
                                    الطالب {!! $sortIcon('student') !!}
                                </a>
                            </th>
                            <th class="px-4 py-3 text-right font-semibold">
                                <a href="{{ $sortLink('circle') }}" class="inline-flex items-center hover:text-red-900">
                                    الحلقة {!! $sortIcon('circle') !!}
                                </a>
                            </th>
                            <th class="px-4 py-3 text-right font-semibold">السورة</th>
                            <th class="px-3 py-3 text-center font-semibold">النوع</th>
                            <th class="px-4 py-3 text-right font-semibold">المعلم</th>
                            <th class="px-3 py-3 text-center font-semibold">
                                <a href="{{ $sortLink('percentage') }}" class="inline-flex items-center hover:text-red-900">
                                    النسبة {!! $sortIcon('percentage') !!}
                                </a>
                            </th>
                            <th class="px-3 py-3 text-center font-semibold">
                                <a href="{{ $sortLink('test_date') }}" class="inline-flex items-center hover:text-red-900">
                                    تاريخ الاختبار {!! $sortIcon('test_date') !!}
                                </a>
                            </th>
                            <th class="px-3 py-3 text-center font-semibold">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($results as $result)
                        <tr class="hover:bg-red-50/40 transition-colors">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $result->student?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $result->student?->circle?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $result->surahTest?->surah?->name_arabic ?? '—' }}</td>
                            <td class="px-3 py-3 text-center">
                                @if($result->surahTest?->test_type === 'group')
                                <span class="inline-block px-2 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-medium">جماعي</span>
                                @else
                                <span class="inline-block px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-medium">فردي</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $result->surahTest?->teacher?->user?->name ?? '—' }}</td>
                            <td class="px-3 py-3 text-center">
                                <span class="font-bold text-red-600">{{ $result->percentage }}%</span>
                            </td>
                            <td class="px-3 py-3 text-center text-gray-600">{{ $result->surahTest?->test_date?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    @if($result->surahTest)
                                    <a href="{{ route('surah-tests.show', ['surah_test' => $result->surahTest, 'student_id' => $result->student_id]) }}"
                                        class="text-[#0a5c36] hover:underline text-xs font-medium">عرض</a>
                                    @can('update surah tests')
                                    <a href="{{ route('surah-tests.edit', $result->surahTest) }}"
                                        class="text-blue-600 hover:underline text-xs font-medium">تعديل</a>
                                    @endcan
                                    @endif
                                    @if($result->student)
                                    <a href="{{ route('students.show', $result->student) }}"
                                        class="text-gray-600 hover:underline text-xs font-medium">ملف الطالب</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                                <p class="text-sm">لا يوجد طلاب بمستوى "إعادة" حاليًا ✓</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
</div>

        <x-pagination :paginator="$results" />

    </div>
</x-layouts.markaz-layout>