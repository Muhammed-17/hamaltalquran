<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white shadow-xl mb-8">
        <h1 class="text-3xl font-black mb-2">الأوسمة الناقصة</h1>
        <p class="text-emerald-100/80 text-sm font-medium">{{ $shortages->total() }} سجل عجز — افتراضيًا تُعرض السجلات المفتوحة فقط</p>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6" dir="rtl">
        <form method="GET" class="flex flex-col lg:flex-row gap-4 items-end">
            <div class="w-full lg:flex-1">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">بحث باسم الطالب</label>
                <input type="search" name="q" value="{{ request('q') }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
            </div>
            <div class="w-full lg:w-48">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">نوع الوسام</label>
                <select name="badge_type_id" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    <option value="">الكل</option>
                    @foreach ($types as $type)<option value="{{ $type->id }}" @selected(request('badge_type_id') == $type->id)>{{ $type->name }}</option>@endforeach
                </select>
            </div>
            <div class="w-full lg:w-44">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">حالة العجز</label>
                <select name="status" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    <option value="">المفتوحة فقط</option>
                    @foreach (\App\Enums\Badges\ShortageStatus::cases() as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>@endforeach
                </select>
            </div>
            <button class="px-6 py-2.5 bg-[#0a5c36] text-white font-bold rounded-xl text-sm">تصفية</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto" dir="rtl">
        <table class="w-full text-right">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">الطالب</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">الحلقة</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">المعلم</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">النوع</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">المستحق</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">المسلّم</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">الناقص</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">تاريخ الاستحقاق</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">الحالة</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($shortages as $s)
                    <tr>
                        <td class="px-6 py-4 text-sm font-bold">{{ $s->student->name }}</td>
                        <td class="px-6 py-4 text-sm">{{ $s->entitlement->circle->name }}</td>
                        <td class="px-6 py-4 text-sm">{{ $s->teacher->name }}</td>
                        <td class="px-6 py-4 text-sm">{{ $s->badgeType->name }}</td>
                        <td class="px-6 py-4 text-sm">{{ $s->required_quantity }}</td>
                        <td class="px-6 py-4 text-sm">{{ $s->delivered_quantity }}</td>
                        <td class="px-6 py-4 text-sm font-black text-red-600">{{ $s->shortage_quantity }}</td>
                        <td class="px-6 py-4 text-sm">{{ $s->entitlement->entitlement_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-4 text-sm">{{ $s->status->label() }}</td>
                        <td class="px-6 py-4 text-sm"><a href="{{ route('badges.shortages.show', $s) }}" class="text-[#0a5c36] font-bold hover:underline">استكمال</a></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-6 py-10 text-center text-gray-400 text-sm">لا أوسمة ناقصة مطابقة</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6"><x-pagination :paginator="$shortages" /></div>
</x-layouts.markaz-layout>
