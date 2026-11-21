<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6 mb-8">
        <div class="text-right w-full md:w-auto z-10">
            <h1 class="text-3xl font-black mb-2">تسليم الأوسمة للمعلمين</h1>
            <p class="text-emerald-100/80 text-sm font-medium">{{ $handovers->total() }} عملية تسليم</p>
        </div>
        @can('badges.handovers.create')
            <a href="{{ route('badges.handovers.create') }}" class="px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-2xl shadow-lg active:scale-95">+ تسليم جديد</a>
        @endcan
        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6" dir="rtl">
        <form method="GET" class="flex flex-col lg:flex-row gap-4 items-end">
            <div class="w-full lg:w-56">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">المعلم</label>
                <select name="teacher_id" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    <option value="">كل المعلمين</option>
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
            <button class="px-6 py-2.5 bg-[#0a5c36] text-white font-bold rounded-xl text-sm">تصفية</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto" dir="rtl">
        <table class="w-full text-right">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">المعلم</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">النوع</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">الكمية</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">تاريخ التسليم</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">الحالة</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($handovers as $h)
                    <tr>
                        <td class="px-6 py-4 text-sm font-bold">{{ $h->teacher->name }}</td>
                        <td class="px-6 py-4 text-sm">{{ $h->badgeType->name }}</td>
                        <td class="px-6 py-4 text-sm font-black">{{ $h->quantity }}</td>
                        <td class="px-6 py-4 text-sm">{{ $h->handover_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-4 text-sm">
                            @if ($h->status === 'active')<span class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-bold">نشط</span>
                            @else<span class="px-2 py-1 rounded-lg bg-red-50 text-red-600 text-xs font-bold">ملغي</span>@endif
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <span class="flex gap-2 items-center">
                                <a href="{{ route('badges.handovers.show', $h) }}" class="text-[#0a5c36] font-bold hover:underline">عرض</a>
                                @can('cancel', $h)
                                    @if ($h->status === 'active')
                                        <form method="POST" action="{{ route('badges.handovers.cancel', $h) }}" onsubmit="return confirm('إلغاء هذا التسليم؟ سيُخصم من عهدة المعلم.')">
                                            @csrf
                                            <button class="text-red-600 font-bold hover:underline">إلغاء</button>
                                        </form>
                                    @endif
                                @endcan
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400 text-sm">لا تسليمات مطابقة</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6"><x-pagination :paginator="$handovers" /></div>
</x-layouts.markaz-layout>
