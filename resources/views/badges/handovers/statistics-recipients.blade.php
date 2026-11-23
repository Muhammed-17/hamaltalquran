<x-layouts.markaz-layout>
    <div class="max-w-6xl mx-auto px-3 sm:px-0" dir="rtl">
        <a href="{{ route('badges.handovers.statistics', request()->query()) }}" class="inline-flex items-center gap-1.5 text-sm font-bold text-gray-500 hover:text-[#0a5c36] mb-4 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            رجوع للإحصائيات
        </a>

        <div class="flex flex-wrap items-center gap-3 mb-5">
            <div>
                <h1 class="text-xl sm:text-2xl font-black">تفاصيل المستلمين</h1>
                <p class="text-sm text-gray-500 mt-0.5">المستلم لكل نوع وسام لكل مستلم — مرتّبة بإجمالي المستلم</p>
            </div>
            @if (auth()->user()->hasRole('admin'))
                <a href="{{ route('badges.handovers.report', array_diff_key(request()->query(), ['q' => true])) }}" class="mr-auto px-5 py-2.5 bg-[#0a5c36] hover:bg-[#0b3d2c] text-white font-bold rounded-xl text-sm transition active:scale-95">تنزيل التقرير (Excel)</a>
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6">
            <form method="GET" class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">بحث باسم المستلم</label>
                    <input type="search" name="q" value="{{ request('q') }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
                </div>
                <div class="flex flex-col lg:flex-row lg:flex-wrap gap-3 items-end">
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
                            :options="$filterTypes->map(fn($t) => ['value' => $t->id, 'label' => $t->name])->values()"
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
                        @if (request()->hasAny(['q', 'month', 'teacher_id', 'badge_type_id', 'status']))
                            <a href="{{ route('badges.handovers.statistics-recipients') }}" class="px-4 py-2.5 bg-gray-100 text-gray-500 font-bold rounded-xl text-sm">مسح</a>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        {{-- بطاقات إجمالية فوق الجدول (من الصفوف المعروضة بعد البحث) --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            @foreach ([
                ['عدد المستلمين', $matrix->count(), 'text-gray-800'],
                ['إجمالي المستلم', (int) $matrix->sum('total'), 'text-emerald-700'],
                ['إجمالي العمليات', (int) $matrix->sum('ops'), 'text-gray-800'],
                ['إجمالي الملغي', (int) $matrix->sum('cancelled'), 'text-red-600'],
            ] as [$label, $value, $cls])
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <div class="text-xs font-bold text-gray-400 mb-1">{{ $label }}</div>
                    <div class="text-2xl font-black {{ $cls }}">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto">
            @php $tints = ['#eef2ff', '#fef3c7', '#dcfce7', '#ffe4e6', '#e0f2fe', '#f3e8ff', '#fef9c3', '#ffedd5']; @endphp
            <table class="w-full text-right">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-3 text-sm font-bold text-gray-600">المستلم</th>
                        @foreach ($types as $type)
                            <th class="px-5 py-3 text-sm font-bold text-gray-700 text-center border-x border-gray-100" style="background-color:{{ $tints[$loop->index % count($tints)] }}">{{ $type->name }}</th>
                        @endforeach
                        <th class="px-5 py-3 text-sm font-bold text-gray-700 text-center" style="background-color:#f3f4f6">الإجمالي</th>
                        <th class="px-5 py-3 text-sm font-bold text-gray-700 text-center">عمليات</th>
                        <th class="px-5 py-3 text-sm font-bold text-gray-700 text-center">ملغي</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($matrix as $teacherId => $row)
                        <tr @if ($loop->even) style="background-color:#f9fafb" @endif>
                            <td class="px-5 py-3 text-sm font-bold">{{ $users[$teacherId]?->name ?? '—' }}</td>
                            @foreach ($types as $type)
                                @php $qty = (int) ($row['types'][$type->id] ?? 0); @endphp
                                <td class="px-5 py-3 text-sm text-center font-bold" style="color:{{ $qty > 0 ? '#047857' : '#d1d5db' }}">{{ $qty }}</td>
                            @endforeach
                            <td class="px-5 py-3 text-sm text-center font-black" style="background-color:#f3f4f6;color:#065f46">{{ $row['total'] }}</td>
                            <td class="px-5 py-3 text-sm text-center">{{ $row['ops'] }}</td>
                            <td class="px-5 py-3 text-sm text-center font-bold" style="color:{{ $row['cancelled'] > 0 ? '#dc2626' : '#d1d5db' }}">{{ $row['cancelled'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 4 + $types->count() }}" class="px-5 py-8 text-center text-gray-400 text-sm">لا بيانات مطابقة للفلاتر</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.markaz-layout>
