<!-- ═══════════════════════════════════════ -->
<!-- فلاتر -->
<!-- ═══════════════════════════════════════ -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">

    <form method="GET" action="{{ $filterAction }}"
        class="grid grid-cols-1 sm:grid-cols-{{ $testType === 'group' ? 4 : 5 }} gap-4 items-end">


        @if($testType === 'individual')
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">اسم الطالب</label>
            <input type="text" name="student_name" value="{{ request('student_name') }}"
                placeholder="ابحث باسم الطالب..."
                class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm px-4 py-2.5">
        </div>
        @endif

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">اسم الحلقة</label>
            <x-searchable-select
                name="circle_id"
                :options="$circleOptions"
                :defaultValue="request('circle_id', '')"
                placeholder="كل الحلقات"
                searchPlaceholder="ابحث باسم الحلقة..." />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">السورة</label>
            <x-searchable-select
                name="surah_id"
                :options="$surahOptions"
                :defaultValue="request('surah_id', '')"
                placeholder="كل السور"
                searchPlaceholder="ابحث باسم السورة..." />
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
            @if(request()->anyFilled(['student_name', 'circle_id', 'center_id', 'surah_id']))
            <a href="{{ $filterAction }}"
                class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm hover:bg-gray-50 transition-colors">
                إعادة تعيين
            </a>
            @endif
        </div>
    </form>
</div>

<!-- ═══════════════════════════════════════ -->
<!-- جدول الاختبارات -->
<!-- ═══════════════════════════════════════ -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            @php
            $currentSort = request('sort', 'test_date');
            $currentDir = request('dir', 'desc');
            $sortLink = fn($field) => $filterAction . '?' . http_build_query(
            array_merge(request()->except(['sort', 'dir', 'page']), [
            'sort' => $field,
            'dir' => ($currentSort === $field && $currentDir === 'asc') ? 'desc' : 'asc',
            ])
            );
            $sortIcon = fn($field) => $currentSort !== $field
            ? '<i class="fas fa-sort text-gray-300 text-[10px] mr-1"></i>'
            : ($currentDir === 'asc'
            ? '<i class="fas fa-sort-up text-[#0a5c36] text-[10px] mr-1"></i>'
            : '<i class="fas fa-sort-down text-[#0a5c36] text-[10px] mr-1"></i>');
            @endphp
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="px-4 py-3 text-right font-semibold">
                        <a href="{{ $sortLink('surah') }}" class="inline-flex items-center hover:text-[#0a5c36]">
                            السورة {!! $sortIcon('surah') !!}
                        </a>
                    </th>
                    <th class="px-4 py-3 text-right font-semibold">
                        @if($testType === 'group')
                        <a href="{{ $sortLink('circle') }}" class="inline-flex items-center hover:text-[#0a5c36]">
                            الحلقة {!! $sortIcon('circle') !!}
                        </a>
                        @else
                        الطالب
                        @endif
                    </th>
                    <th class="px-4 py-3 text-right font-semibold">
                        <a href="{{ $sortLink('teacher') }}" class="inline-flex items-center hover:text-[#0a5c36]">
                            المعلم {!! $sortIcon('teacher') !!}
                        </a>
                    </th>
                    <th class="px-3 py-3 text-center font-semibold">
                        <a href="{{ $sortLink('percentage') }}" class="inline-flex items-center hover:text-[#0a5c36]">
                            {{ $testType === 'group' ? 'متوسط النسبة' : 'النسبة' }} {!! $sortIcon('percentage') !!}
                        </a>
                    </th>
                    <th class="px-3 py-3 text-center font-semibold">
                        <a href="{{ $sortLink('test_date') }}" class="inline-flex items-center hover:text-[#0a5c36]">
                            التاريخ {!! $sortIcon('test_date') !!}
                        </a>
                    </th>
                    <th class="px-3 py-3 text-center font-semibold">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($tests as $test)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $test->surah?->name_arabic ?? '—' }}</td>

                    <td class="px-4 py-3 text-gray-600">
                        @if($testType === 'group')
                        {{ $test->circle?->name ?? '—' }}
                        @else
                        {{ $test->results->first()?->student?->name ?? '—' }}
                        @endif
                    </td>

                    <td class="px-4 py-3 text-gray-600">{{ $test->teacher?->user?->name ?? $test->teacher?->name ?? '—' }}</td>

                    <td class="px-3 py-3 text-center">
                        @if($test->results_avg_percentage !== null)
                        <span class="font-bold text-emerald-600">{{ round($test->results_avg_percentage) }}%</span>
                        @else
                        <span class="text-gray-300">—</span>
                        @endif
                    </td>

                    <td class="px-3 py-3 text-center text-gray-600">{{ $test->test_date?->format('Y-m-d') ?? '—' }}</td>

                    <td class="px-3 py-3">
                        <div class="flex items-center justify-center gap-2">
                            @can('view surah tests')
                            <a href="{{ route('surah-tests.show', $test) }}"
                                class="text-[#0a5c36] hover:underline text-xs font-medium">عرض</a>
                            @endcan
                            @can('update surah tests')
                            <a href="{{ route('surah-tests.edit', $test) }}"
                                class="text-blue-600 hover:underline text-xs font-medium">تعديل</a>
                            @endcan
                            @can('delete surah tests')
                            <form action="{{ route('surah-tests.destroy', $test) }}" method="POST"
                                onsubmit="return confirm('هل أنت متأكد من حذف هذا الاختبار؟');" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline text-xs font-medium">حذف</button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                        <p class="text-sm">لا توجد اختبارات مسجلة بعد.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<x-pagination :paginator="$tests" />