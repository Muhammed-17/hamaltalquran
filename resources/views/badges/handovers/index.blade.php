<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6 mb-8">
        <div class="text-right w-full md:w-auto z-10">
            <h1 class="text-3xl font-black mb-2">تسليم الأوسمة للمعلمين</h1>
            <p class="text-emerald-100/80 text-sm font-medium">{{ $paginator->total() }} عملية تسليم</p>
        </div>
        <div class="flex flex-wrap gap-2.5 z-10">
            @can('badges.handovers.create')
                <a href="{{ route('badges.handovers.create') }}" class="px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-2xl shadow-lg active:scale-95">+ تسليم جديد</a>
            @endcan
            @can('badges.handovers.view')
                <a href="{{ route('badges.handovers.statistics', array_filter(['month' => request('month'), 'teacher_id' => request('teacher_id'), 'badge_type_id' => request('badge_type_id'), 'status' => request('status')])) }}" class="px-5 py-3 bg-white/10 hover:bg-white/20 border border-white/25 text-white font-bold rounded-2xl transition active:scale-95">الإحصائيات</a>
            @endcan
            @can('badges.reports.view')
                <a href="{{ route('badges.handovers.report', array_filter(['month' => request('month'), 'teacher_id' => request('teacher_id'), 'badge_type_id' => request('badge_type_id'), 'status' => request('status')])) }}" class="px-5 py-3 bg-white/10 hover:bg-white/20 border border-white/25 text-white font-bold rounded-2xl transition active:scale-95">التقرير</a>
            @endcan
        </div>
        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6" dir="rtl">
        <form method="GET" class="flex flex-col lg:flex-row gap-4 items-end">
            <div class="w-full lg:w-56">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">المستلم</label>
                <select name="teacher_id" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    <option value="">كل المستلمين</option>
                    @foreach ($teachers as $t)<option value="{{ $t->id }}" @selected(request('teacher_id') == $t->id)>{{ $t->name }}</option>@endforeach
                </select>
            </div>
            <div class="w-full lg:w-48">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">نوع الوسام</label>
                <select name="badge_type_id" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    <option value="">الكل</option>
                    @foreach ($types as $type)<option value="{{ $type->id }}" @selected(request('badge_type_id') == $type->id)>{{ $type->name }}</option>@endforeach
                </select>
            </div>
            <div class="w-full lg:w-40">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">الحالة</label>
                <select name="status" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    <option value="">الكل</option>
                    <option value="active" @selected(request('status') === 'active')>نشط</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>ملغي</option>
                </select>
            </div>
            <div class="w-full lg:w-44">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">الشهر</label>
                <input type="month" name="month" value="{{ request('month') }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
            </div>
            <button class="px-6 py-2.5 bg-[#0a5c36] text-white font-bold rounded-xl text-sm">تصفية</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto" dir="rtl">
        <table class="w-full text-right">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">المستلم</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">النوع</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">الكمية</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">تاريخ التسليم</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">الحالة</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($groups as $group)
                    @php
                        $items = $group['items'];
                        $first = $items->first();
                        $activeItems = $items->where('status', 'active');
                        $allCancelled = $activeItems->isEmpty();
                        $someCancelled = !$allCancelled && $items->where('status', 'cancelled')->isNotEmpty();
                    @endphp
                    <tr>
                        <td class="px-6 py-4 text-sm font-bold">{{ $first->teacher->name }}<div class="text-[11px] font-normal text-gray-400">من: {{ $first->sourceUser?->name ?? 'الإدارة المركزية' }}</div></td>
                        <td class="px-6 py-4 text-sm">
                            @foreach ($items as $h)
                                <div class="py-0.5">
                                    {{ $h->badgeType->name }}
                                    @if ($h->status === 'cancelled')<span class="px-1.5 py-0.5 rounded-md bg-red-50 text-red-600 text-[10px] font-bold">ملغي</span>@endif
                                </div>
                            @endforeach
                        </td>
                        <td class="px-6 py-4 text-sm">
                            @foreach ($items as $h)
                                <div class="py-0.5 font-black {{ $h->status === 'cancelled' ? 'line-through text-gray-300' : '' }}">{{ $h->quantity }}</div>
                            @endforeach
                            @if ($items->count() > 1)
                                <div class="border-t border-gray-100 mt-1 pt-1 text-xs font-bold text-gray-500">الإجمالي: {{ $activeItems->sum('quantity') }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm">{{ $first->handover_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-4 text-sm">
                            @if ($allCancelled)<span class="px-2 py-1 rounded-lg bg-red-50 text-red-600 text-xs font-bold">ملغي</span>
                            @else<span class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-bold">نشط</span>@endif
                            @if ($someCancelled)<div class="text-[10px] text-gray-400 mt-1">إلغاء جزئي</div>@endif
                        </td>
                        <td class="px-6 py-4 text-sm">
                            @foreach ($items as $h)
                                <span class="flex gap-2 items-center py-0.5">
                                    <a href="{{ route('badges.handovers.show', $h) }}" class="text-[#0a5c36] font-bold hover:underline">عرض</a>
                                    @can('cancel', $h)
                                        @if ($h->status === 'active')
                                            <form method="POST" action="{{ route('badges.handovers.cancel', $h) }}" onsubmit="return confirm('إلغاء تسليم {{ $h->badgeType->name }}؟ سيُخصم من عهدة المستلم.')">
                                                @csrf
                                                <button class="text-red-600 font-bold hover:underline">إلغاء</button>
                                            </form>
                                        @endif
                                    @endcan
                                </span>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400 text-sm">لا تسليمات مطابقة</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6"><x-pagination :paginator="$paginator" /></div>
</x-layouts.markaz-layout>
