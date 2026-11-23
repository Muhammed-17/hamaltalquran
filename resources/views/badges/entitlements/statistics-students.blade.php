<x-layouts.markaz-layout>
    <div class="max-w-6xl mx-auto px-3 sm:px-0" dir="rtl">
        <a href="{{ route('badges.entitlements.statistics', request()->query()) }}" class="inline-flex items-center gap-1.5 text-sm font-bold text-gray-500 hover:text-[#0a5c36] mb-4 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            رجوع للإحصائيات
        </a>

        <div class="flex flex-wrap items-center gap-3 mb-5">
            <div>
                <h1 class="text-xl sm:text-2xl font-black">تفاصيل الطلاب الحاصلين على الأوسمة</h1>
                <p class="text-sm text-gray-500 mt-0.5">المستحق والموزّع لكل نوع وسام لكل طالب مع حلقته — مرتّبة بإجمالي الموزّع</p>
            </div>
            @if (auth()->user()->hasRole('admin'))
                <a href="{{ route('badges.entitlements.report', request()->query()) }}" class="mr-auto px-5 py-2.5 bg-[#0a5c36] hover:bg-[#0b3d2c] text-white font-bold rounded-xl text-sm transition active:scale-95">تنزيل التقرير (Excel)</a>
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6">
            <form method="GET" class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">بحث باسم الطالب</label>
                    <input type="search" name="q" value="{{ request('q') }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
                </div>
                <div class="flex flex-col lg:flex-row lg:flex-wrap gap-3 items-end">
                    <div class="w-full lg:flex-1 lg:min-w-[140px]">
                        <label class="block text-xs font-bold text-gray-400 mb-1.5">الشهر</label>
                        <input type="month" name="month" value="{{ request('month') }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    </div>
                    <div class="w-full lg:flex-1 lg:min-w-[140px]">
                        <label class="block text-xs font-bold text-gray-400 mb-1.5">المسؤول عن التوزيع</label>
                        <x-searchable-select
                            name="teacher_id"
                            :options="$distributors->map(fn($d) => ['value' => $d->id, 'label' => $d->name])->values()"
                            placeholder="كل المسؤولين"
                            searchPlaceholder="ابحث عن مسؤول..."
                            :defaultValue="request('teacher_id', '')" />
                    </div>
                    <div class="w-full lg:flex-1 lg:min-w-[140px]">
                        <label class="block text-xs font-bold text-gray-400 mb-1.5">الحلقة</label>
                        <x-searchable-select
                            name="circle_id"
                            :options="$circles->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values()"
                            placeholder="كل الحلقات"
                            searchPlaceholder="ابحث عن حلقة..."
                            :defaultValue="request('circle_id', '')" />
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
                            @foreach (\App\Enums\Badges\EntitlementStatus::cases() as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>@endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button class="px-6 py-2.5 bg-[#0a5c36] text-white font-bold rounded-xl text-sm">تصفية</button>
                        @if (request()->hasAny(['q', 'month', 'teacher_id', 'circle_id', 'badge_type_id', 'status']))
                            <a href="{{ route('badges.entitlements.statistics-students') }}" class="px-4 py-2.5 bg-gray-100 text-gray-500 font-bold rounded-xl text-sm">مسح</a>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        {{-- بطاقات إجمالية فوق الجدول --}}
        @php $sumEntitled = collect($matrix)->sum('total_entitled'); $sumDelivered = collect($matrix)->sum('total_delivered'); @endphp
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            @foreach ([
                ['عدد الطلاب', count($matrix), 'text-gray-800'],
                ['إجمالي المستحق', $sumEntitled, 'text-amber-600'],
                ['إجمالي الموزّع', $sumDelivered, 'text-emerald-700'],
                ['إجمالي المتبقي', $sumEntitled - $sumDelivered - collect($matrix)->sum('redeemed'), 'text-red-600'],
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
                        <th rowspan="2" class="px-5 py-3 text-sm font-bold text-gray-600 align-middle">الطالب</th>
                        <th rowspan="2" class="px-5 py-3 text-sm font-bold text-gray-600 align-middle">الحلقة</th>
                        @foreach ($types as $type)
                            <th colspan="2" class="px-5 py-3 text-sm font-bold text-gray-700 text-center border-x border-gray-100" style="background-color:{{ $tints[$loop->index % count($tints)] }}">{{ $type->name }}</th>
                        @endforeach
                        <th colspan="2" class="px-5 py-3 text-sm font-bold text-gray-700 text-center" style="background-color:#f3f4f6">الإجمالي</th>
                    </tr>
                    <tr>
                        @foreach ($types as $type)
                            <th class="px-5 py-2 text-xs font-bold text-gray-600 text-center" style="background-color:{{ $tints[$loop->index % count($tints)] }}">مستحق</th>
                            <th class="px-5 py-2 text-xs font-bold text-gray-600 text-center" style="background-color:{{ $tints[$loop->index % count($tints)] }}">موزّع</th>
                        @endforeach
                        <th class="px-5 py-2 text-xs font-bold text-gray-600 text-center" style="background-color:#f3f4f6">مستحق</th>
                        <th class="px-5 py-2 text-xs font-bold text-gray-600 text-center" style="background-color:#f3f4f6">موزّع</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($matrix as $gid => $row)
                        <tr @if ($loop->even) style="background-color:#f9fafb" @endif>
                            <td class="px-5 py-3 text-sm font-bold">{{ $students[$gid]?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-sm">{{ $students[$gid]?->circle?->name ?? '—' }}</td>
                            @foreach ($types as $type)
                                @php $ent = (int) ($row['entitled'][$type->id] ?? 0); $del = (int) ($row['delivered'][$type->id] ?? 0); @endphp
                                <td class="px-5 py-3 text-sm text-center font-bold" style="color:{{ $ent > 0 ? '#b45309' : '#d1d5db' }}">{{ $ent }}</td>
                                <td class="px-5 py-3 text-sm text-center font-bold" style="color:{{ $del > 0 ? '#047857' : '#d1d5db' }}">{{ $del }}</td>
                            @endforeach
                            <td class="px-5 py-3 text-sm text-center font-black" style="background-color:#f3f4f6">{{ $row['total_entitled'] }}</td>
                            <td class="px-5 py-3 text-sm text-center font-black" style="background-color:#f3f4f6;color:#065f46">{{ $row['total_delivered'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 4 + $types->count() * 2 }}" class="px-5 py-8 text-center text-gray-400 text-sm">لا بيانات مطابقة للفلاتر</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.markaz-layout>
