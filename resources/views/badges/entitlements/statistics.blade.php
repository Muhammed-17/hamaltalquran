<x-layouts.markaz-layout>
    <div class="max-w-6xl mx-auto px-3 sm:px-0" dir="rtl">
        <a href="{{ route('badges.entitlements.index') }}" class="inline-flex items-center gap-1.5 text-sm font-bold text-gray-500 hover:text-[#0a5c36] mb-4 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            رجوع للاستحقاقات والتوزيعات
        </a>

        <div class="flex flex-wrap items-center gap-3 mb-5">
            <div>
                <h1 class="text-xl sm:text-2xl font-black">إحصائيات الاستحقاقات والتوزيعات</h1>
                <p class="text-sm text-gray-500 mt-0.5">كل الأرقام محسوبة حسب الفلاتر الحالية</p>
            </div>
            @can('badges.reports.view')
                <a href="{{ route('badges.entitlements.report', request()->query()) }}" class="mr-auto px-5 py-2.5 bg-[#0a5c36] hover:bg-[#0b3d2c] text-white font-bold rounded-xl text-sm transition active:scale-95">تنزيل التقرير (Excel)</a>
            @endcan
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6">
            <form method="GET" class="flex flex-col lg:flex-row lg:flex-wrap gap-3 items-end">
                <div class="w-full lg:flex-1 lg:min-w-[140px]">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">الشهر</label>
                    <input type="month" name="month" value="{{ request('month') }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                </div>
                <div class="w-full lg:flex-1 lg:min-w-[140px]">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">المسؤول عن التوزيع</label>
                    <select name="teacher_id" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                        <option value="">كل المسؤولين</option>
                        @foreach ($distributors as $d)<option value="{{ $d->id }}" @selected((string) request('teacher_id') === (string) $d->id)>{{ $d->name }}</option>@endforeach
                    </select>
                </div>
                <div class="w-full lg:flex-1 lg:min-w-[140px]">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">الحلقة</label>
                    <select name="circle_id" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                        <option value="">كل الحلقات</option>
                        @foreach ($circles as $c)<option value="{{ $c->id }}" @selected((string) request('circle_id') === (string) $c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div class="w-full lg:flex-1 lg:min-w-[140px]">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">نوع الوسام</label>
                    <select name="badge_type_id" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                        <option value="">كل الأنواع</option>
                        @foreach ($types as $t)<option value="{{ $t->id }}" @selected((string) request('badge_type_id') === (string) $t->id)>{{ $t->name }}</option>@endforeach
                    </select>
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
                    @if (request()->hasAny(['month', 'teacher_id', 'circle_id', 'badge_type_id', 'status']))
                        <a href="{{ route('badges.entitlements.statistics') }}" class="px-4 py-2.5 bg-gray-100 text-gray-500 font-bold rounded-xl text-sm">مسح</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            @foreach ([
                ['إجمالي المستحق', $totals['entitled'], 'text-gray-800'],
                ['إجمالي الموزّع', $totals['delivered'], 'text-emerald-700'],
                ['إجمالي المتبقي', $totals['remaining'], 'text-amber-600'],
                ['نسبة الإنجاز', $totals['pct'] . '%', 'text-[#0a5c36]'],
                ['عمليات الإعطاء', $totals['batches'], 'text-gray-800'],
                ['عدد الاستحقاقات', $totals['entitlements'], 'text-gray-800'],
                ['مكتملة / جزئية / معلّقة', $totals['completed'] . ' / ' . $totals['partial'] . ' / ' . $totals['pending'], 'text-gray-800'],
                ['كمية العجز المفتوح', $totals['shortage_qty'], 'text-red-600'],
            ] as [$label, $value, $cls])
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <div class="text-xs font-bold text-gray-400 mb-1">{{ $label }}</div>
                    <div class="text-2xl font-black {{ $cls }}">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        <div class="h-3 bg-gray-100 rounded-full overflow-hidden mb-8">
            <div class="h-full rounded-full bg-gradient-to-l from-emerald-600 to-emerald-400" style="width: {{ $totals['pct'] }}%"></div>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto">
                <div class="px-6 py-4 font-black">حسب نوع الوسام</div>
                <table class="w-full text-right">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">النوع</th>
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">المستحق</th>
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">الموزّع</th>
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">المتبقي</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($types->filter(fn ($t) => isset($perTypeEntitled[$t->id])) as $type)
                            @php $del = (int) ($perTypeDelivered[$type->id] ?? 0); $qty = (int) $perTypeEntitled[$type->id]->qty; @endphp
                            <tr>
                                <td class="px-5 py-3 text-sm font-bold">{{ $type->name }}</td>
                                <td class="px-5 py-3 text-sm font-black">{{ $qty }}</td>
                                <td class="px-5 py-3 text-sm text-emerald-700 font-bold">{{ $del }}</td>
                                <td class="px-5 py-3 text-sm font-bold {{ $qty - $del > 0 ? 'text-amber-600' : 'text-gray-400' }}">{{ $qty - $del }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-8 text-center text-gray-400 text-sm">لا بيانات مطابقة للفلاتر</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto">
                <div class="px-6 py-4 font-black">حسب المسؤول عن التوزيع</div>
                <table class="w-full text-right">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">المسؤول</th>
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">عمليات</th>
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">المستحق</th>
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">الموزّع</th>
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">المتبقي</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($perDistributorEntitled as $teacherId => $row)
                            @php $del = (int) ($perDistributorDelivered[$teacherId] ?? 0); $qty = (int) $row->qty; @endphp
                            <tr>
                                <td class="px-5 py-3 text-sm font-bold">{{ $distributorUsers[$teacherId]?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm">{{ (int) $row->ops }}</td>
                                <td class="px-5 py-3 text-sm font-black">{{ $qty }}</td>
                                <td class="px-5 py-3 text-sm text-emerald-700 font-bold">{{ $del }}</td>
                                <td class="px-5 py-3 text-sm font-bold {{ $qty - $del > 0 ? 'text-amber-600' : 'text-gray-400' }}">{{ $qty - $del }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400 text-sm">لا بيانات مطابقة للفلاتر</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.markaz-layout>
