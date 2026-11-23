<x-layouts.markaz-layout>
    <div class="max-w-6xl mx-auto px-3 sm:px-0" dir="rtl">
        <a href="{{ route('badges.handovers.index') }}" class="inline-flex items-center gap-1.5 text-sm font-bold text-gray-500 hover:text-[#0a5c36] mb-4 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            رجوع لتسليمات العهدة
        </a>

        <div class="flex flex-wrap items-center gap-3 mb-5">
            <div>
                <h1 class="text-xl sm:text-2xl font-black">إحصائيات تسليمات العهدة</h1>
                <p class="text-sm text-gray-500 mt-0.5">كل الأرقام محسوبة حسب الفلاتر الحالية</p>
            </div>
            @if (auth()->user()->hasRole('admin'))
                <a href="{{ route('badges.handovers.report', request()->query()) }}" class="mr-auto px-5 py-2.5 bg-[#0a5c36] hover:bg-[#0b3d2c] text-white font-bold rounded-xl text-sm transition active:scale-95">تنزيل التقرير (Excel)</a>
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6">
            <form method="GET" class="flex flex-col lg:flex-row lg:flex-wrap gap-3 items-end">
                <div class="w-full lg:flex-1 lg:min-w-[140px]">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">الشهر</label>
                    <input type="month" name="month" value="{{ request('month') }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                </div>
                <div class="w-full lg:flex-1 lg:min-w-[140px]">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">المستلم</label>
                    <x-searchable-select
                        name="teacher_id"
                        :options="$recipients->map(fn($r) => ['value' => $r->id, 'label' => $r->name])->values()"
                        placeholder="كل المستلمين"
                        searchPlaceholder="ابحث عن مستلم..."
                        :defaultValue="request('teacher_id', '')" />
                </div>
                <div class="w-full lg:flex-1 lg:min-w-[140px]">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">نوع الوسام</label>
                    <x-searchable-select
                        name="badge_type_id"
                        :options="$types->map(fn($t) => ['value' => $t->id, 'label' => $t->name])->values()"
                        placeholder="كل الأنواع"
                        searchPlaceholder="ابحث عن نوع وسام..."
                        :defaultValue="request('badge_type_id', '')" />
                </div>
                <div class="w-full lg:flex-1 lg:min-w-[140px]">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">الحالة</label>
                    <select name="status" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                        <option value="">الكل</option>
                        <option value="active" @selected(request('status') === 'active')>نشط</option>
                        <option value="cancelled" @selected(request('status') === 'cancelled')>ملغي</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button class="px-6 py-2.5 bg-[#0a5c36] text-white font-bold rounded-xl text-sm">تصفية</button>
                    @if (request()->hasAny(['month', 'teacher_id', 'badge_type_id', 'status']))
                        <a href="{{ route('badges.handovers.statistics') }}" class="px-4 py-2.5 bg-gray-100 text-gray-500 font-bold rounded-xl text-sm">مسح</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            @foreach ([
                ['إجمالي المستلم (نشط)', $totals['received'], 'text-emerald-700'],
                ['عمليات التسليم', $totals['operations'], 'text-gray-800'],
                ['عدد المستلمين', $totals['recipients'], 'text-gray-800'],
                ['كمية الملغاة', $totals['cancelled_qty'], 'text-red-600'],
                ['سجلات ملغاة', $totals['cancelled_records'], 'text-red-600'],
            ] as [$label, $value, $cls])
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <div class="text-xs font-bold text-gray-400 mb-1">{{ $label }}</div>
                    <div class="text-2xl font-black {{ $cls }}">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        <div class="flex flex-col gap-6">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto">
                <div class="px-6 py-4 font-black">حسب نوع الوسام</div>
                <table class="w-full text-right">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">النوع</th>
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">عمليات</th>
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">المستلم</th>
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">الملغي</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($types->filter(fn ($t) => isset($perType[$t->id])) as $type)
                            <tr>
                                <td class="px-5 py-3 text-sm font-bold">{{ $type->name }}</td>
                                <td class="px-5 py-3 text-sm">{{ $perType[$type->id]['ops'] }}</td>
                                <td class="px-5 py-3 text-sm font-black text-emerald-700">{{ $perType[$type->id]['qty'] }}</td>
                                <td class="px-5 py-3 text-sm font-bold text-red-600">{{ (int) ($perTypeCancelled[$type->id] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-8 text-center text-gray-400 text-sm">لا بيانات مطابقة للفلاتر</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto">
                <div class="px-6 py-4 flex items-center justify-between gap-3">
                    <span class="font-black">حسب المستلم</span>
                    <a href="{{ route('badges.handovers.statistics-recipients', request()->query()) }}" class="shrink-0 px-4 py-2 bg-[#0a5c36] hover:bg-[#0b3d2c] text-white text-xs font-black rounded-xl transition active:scale-95">عرض المزيد</a>
                </div>
                @php $shownTypes = $types->filter(fn ($t) => isset($perType[$t->id])); @endphp
                <table class="w-full text-right">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">المستلم</th>
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">عمليات</th>
                            @foreach ($shownTypes as $type)
                                <th class="px-5 py-3 text-sm font-bold text-gray-600">{{ $type->name }}</th>
                            @endforeach
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">إجمالي المستلم</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($perRecipient as $teacherId => $row)
                            <tr>
                                <td class="px-5 py-3 text-sm font-bold">{{ $recipientUsers[$teacherId]?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm">{{ $row['ops'] }}</td>
                                @foreach ($shownTypes as $type)
                                    <td class="px-5 py-3 text-sm">{{ $row['types'][$type->id] ?? 0 }}</td>
                                @endforeach
                                <td class="px-5 py-3 text-sm font-black text-emerald-700">{{ $row['qty'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ 3 + $shownTypes->count() }}" class="px-5 py-8 text-center text-gray-400 text-sm">لا بيانات مطابقة للفلاتر</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.markaz-layout>
