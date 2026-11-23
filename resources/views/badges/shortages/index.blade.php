<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white shadow-xl mb-8">
        <h1 class="text-3xl font-black mb-2">الأوسمة الناقصة</h1>
        <p class="text-emerald-100/80 text-sm font-medium">{{ $paginator->total() }} موزّع لديه عجز — {{ $totalRecords }} سجل عجز — تُعرض السجلات المفتوحة فقط</p>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6" dir="rtl">
        <form method="GET">
            <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">بحث باسم الطالب</label>
                    <input type="search" name="q" value="{{ request('q') }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">الشهر (تاريخ الاستحقاق)</label>
                    <input type="month" name="month" value="{{ request('month') }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">الموزّع</label>
                    <x-searchable-select
                        name="teacher_id"
                        :options="$filterTeachers->map(fn($ft) => ['value' => $ft->id, 'label' => $ft->name])->values()"
                        placeholder="كل الموزّعين"
                        searchPlaceholder="ابحث عن موزّع..."
                        :defaultValue="request('teacher_id', '')" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">الحلقة</label>
                    <x-searchable-select
                        name="circle_id"
                        :options="$filterCircles->map(fn($fc) => ['value' => $fc->id, 'label' => $fc->name])->values()"
                        placeholder="كل الحلقات"
                        searchPlaceholder="ابحث عن حلقة..."
                        :defaultValue="request('circle_id', '')" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">نوع الوسام</label>
                    <x-searchable-select
                        name="badge_type_id"
                        :options="$filterTypes->map(fn($ft) => ['value' => $ft->id, 'label' => $ft->name])->values()"
                        placeholder="كل الأنواع"
                        searchPlaceholder="ابحث عن نوع وسام..."
                        :defaultValue="request('badge_type_id', '')" />
                </div>
                <div class="flex items-end gap-2">
                    <button class="px-6 py-2.5 bg-[#0a5c36] text-white font-bold rounded-xl text-sm">تصفية</button>
                    @if (request()->hasAny(['q', 'month', 'teacher_id', 'circle_id', 'badge_type_id']))
                        <a href="{{ route('badges.shortages.index') }}" class="px-4 py-2.5 bg-gray-100 text-gray-600 font-bold rounded-xl text-sm">مسح الفلاتر</a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <div class="space-y-4" dir="rtl">
        @forelse ($groups as $group)
            @php
                $items = $group['items'];
                $teacher = $group['teacher'];
                $studentsCount = $items->pluck('student_id')->unique()->count();
                $perTypeShortage = $items->groupBy('badge_type_id');
            @endphp
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm hover:shadow-md transition overflow-hidden" x-data="{ open: false }">
                <div class="flex items-stretch">
                    <div class="w-1.5 shrink-0" style="background:linear-gradient(to bottom,#7f1d1d,#ef4444,#fbbf24)"></div>
                    <div class="flex-1 min-w-0 px-4 sm:px-6 py-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="w-11 h-11 rounded-2xl text-white flex items-center justify-center font-black text-lg shrink-0 shadow" style="background-color:#991b1b">{{ mb_substr($teacher?->name ?? '؟', 0, 1) }}</div>
                            <div class="min-w-0">
                                <div class="font-black text-[15px] leading-tight truncate">{{ $teacher?->name }}</div>
                                <div class="text-[11px] text-gray-400 mt-0.5">{{ $studentsCount }} طالب مستحق • {{ $items->count() }} سجل عجز</div>
                            </div>
                            @foreach ($perTypeShortage as $typeId => $rows)
                                <span class="text-[11px] font-bold bg-red-50 text-red-700 px-2 py-1 rounded-lg">{{ $rows->first()->badgeType->name }}: ناقص {{ $rows->sum('shortage_quantity') }}</span>
                            @endforeach
                            <span class="text-[11px] font-black text-white px-2.5 py-1 rounded-lg" style="background-color:#b91c1c">إجمالي الناقص: {{ $items->sum('shortage_quantity') }}</span>
                            <div class="mr-auto flex items-center gap-2">
                                @if ($group['canHandover'])
                                    <a href="{{ $group['handoverUrl'] }}" class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#0a5c36] hover:bg-[#0b3d2c] text-white text-xs font-black transition active:scale-95">تسليم عهدة</a>
                                @endif
                                <button type="button" @click="open = !open" class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-gray-50 hover:bg-red-50 text-red-700 text-xs font-black transition">
                                    <span x-text="open ? 'إخفاء الطلاب' : 'عرض الطلاب'"></span>
                                    <svg class="w-3.5 h-3.5 transition-transform duration-300" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div x-show="open" x-cloak class="border-t border-dashed border-gray-200 bg-gray-50/60 overflow-x-auto">
                    <table class="w-full text-right">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-sm font-bold text-gray-600">الطالب</th>
                                <th class="px-6 py-3 text-sm font-bold text-gray-600">الحلقة</th>
                                @foreach ($typesInScope as $t)
                                    <th class="px-6 py-3 text-sm font-bold text-gray-600">{{ $t->name }}</th>
                                @endforeach
                                <th class="px-6 py-3 text-sm font-bold text-gray-600">إجمالي الناقص</th>
                                <th class="px-6 py-3 text-sm font-bold text-gray-600"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($items->groupBy('student_id') as $studentItems)
                                @php $s0 = $studentItems->first(); $byType = $studentItems->keyBy('badge_type_id'); @endphp
                                <tr>
                                    <td class="px-6 py-3 text-sm font-bold">{{ $s0->student->name }}</td>
                                    <td class="px-6 py-3 text-sm">{{ $s0->entitlement->circle->name }}</td>
                                    @foreach ($typesInScope as $t)
                                        <td class="px-6 py-3 text-sm">
                                            @if (isset($byType[$t->id]))
                                                <a href="{{ route('badges.shortages.show', $byType[$t->id]) }}" class="font-black text-red-600 hover:underline" title="استكمال">{{ $byType[$t->id]->shortage_quantity }}</a>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-6 py-3 text-sm font-black text-red-700">{{ $studentItems->sum('shortage_quantity') }}</td>
                                    <td class="px-6 py-3 text-sm">
                                        @php $meta = $group['studentMeta'][$s0->student_id] ?? ['resolvable' => false, 'deliverable' => false]; @endphp
                                        @if ($meta['resolvable'] && $meta['deliverable'])
                                            <a href="{{ route('badges.shortages.deliver', ['teacher_id' => $teacher->id, 'student_id' => $s0->student_id]) }}" class="inline-block px-4 py-1.5 bg-[#0a5c36] hover:bg-[#0b3d2c] text-white text-xs font-black rounded-lg transition active:scale-95">تسليم</a>
                                        @elseif ($meta['resolvable'])
                                            <span class="text-[11px] font-bold text-gray-400">لا رصيد عند الموزّع</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-6 py-10 text-center text-gray-400 text-sm">لا أوسمة ناقصة مطابقة</div>
        @endforelse
    </div>
    <div class="mt-6"><x-pagination :paginator="$paginator" /></div>
</x-layouts.markaz-layout>
