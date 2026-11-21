<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white shadow-xl mb-8">
        <h1 class="text-3xl font-black mb-2">سجل حركة المخزون المركزي</h1>
        <p class="text-emerald-100/80 text-sm font-medium">{{ $movements->total() }} حركة</p>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6" dir="rtl">
        <form method="GET" class="flex flex-col lg:flex-row gap-4 items-end">
            <div class="w-full lg:w-56">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">نوع الوسام</label>
                <select name="badge_type_id" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    <option value="">كل الأنواع</option>
                    @foreach ($types as $type)<option value="{{ $type->id }}" @selected(request('badge_type_id') == $type->id)>{{ $type->name }}</option>@endforeach
                </select>
            </div>
            <div class="w-full lg:w-48">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">نوع الحركة</label>
                <select name="movement_type" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    <option value="">الكل</option>
                    @foreach ($movementTypes as $mt)<option value="{{ $mt->value }}" @selected(request('movement_type') === $mt->value)>{{ $mt->label() }}</option>@endforeach
                </select>
            </div>
            <div class="w-full lg:w-44">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">من تاريخ</label>
                <input type="date" name="from" value="{{ request('from') }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
            </div>
            <div class="w-full lg:w-44">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">إلى تاريخ</label>
                <input type="date" name="to" value="{{ request('to') }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
            </div>
            <button class="px-6 py-2.5 bg-[#0a5c36] text-white font-bold rounded-xl text-sm">تصفية</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto" dir="rtl">
        <table class="w-full text-right">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">النوع</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">الحركة</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">الكمية</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">المصدر</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">ملاحظات</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">بواسطة</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">التاريخ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($movements as $m)
                    <tr>
                        <td class="px-6 py-4 text-sm font-bold">{{ $m->badgeType->name }}</td>
                        <td class="px-6 py-4 text-sm">{{ $m->movement_type->label() }}</td>
                        <td class="px-6 py-4 text-sm font-bold">{{ $m->quantity }}</td>
                        <td class="px-6 py-4 text-sm">{{ $m->source_type === 'teacher' ? 'معلم #' . $m->source_id : 'الإدارة' }}</td>
                        <td class="px-6 py-4 text-sm">{{ $m->notes ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm">{{ $m->creator->name ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm">{{ $m->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-10 text-center text-gray-400 text-sm">لا حركات مطابقة</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6"><x-pagination :paginator="$movements" /></div>
</x-layouts.markaz-layout>
