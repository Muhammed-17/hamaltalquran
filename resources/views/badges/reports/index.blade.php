<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white shadow-xl mb-8">
        <h1 class="text-3xl font-black mb-2">تقارير الأوسمة</h1>
        <p class="text-emerald-100/80 text-sm font-medium">المخزون والأرصدة والتوزيع والتحصيل والمفقود — تُحسب لحظيًا من مصدر الأرصدة الموحد</p>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto mb-8" dir="rtl">
        <div class="px-6 py-4 font-black">ملخص حسب نوع الوسام</div>
        <table class="w-full text-right">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-6 py-3 text-sm font-bold text-gray-600">النوع</th>
                    <th class="px-6 py-3 text-sm font-bold text-gray-600">رصيد الإدارة</th>
                    <th class="px-6 py-3 text-sm font-bold text-gray-600">إجمالي الموزّع</th>
                    <th class="px-6 py-3 text-sm font-bold text-gray-600">إجمالي المحصّل</th>
                    <th class="px-6 py-3 text-sm font-bold text-gray-600">إجمالي المفقود</th>
                    <th class="px-6 py-3 text-sm font-bold text-gray-600">إجمالي التالف</th>
                    <th class="px-6 py-3 text-sm font-bold text-gray-600">حركة النوع</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($types as $type)
                    <tr>
                        <td class="px-6 py-3 text-sm font-bold">{{ $type->name }}</td>
                        <td class="px-6 py-3 text-sm font-black">{{ $adminBalances[$type->id] ?? 0 }}</td>
                        <td class="px-6 py-3 text-sm">{{ $distributedPerType[$type->id] ?? 0 }}</td>
                        <td class="px-6 py-3 text-sm">{{ $collectedPerType[$type->id] ?? 0 }}</td>
                        <td class="px-6 py-3 text-sm text-red-600 font-bold">{{ $missingPerType[$type->id] ?? 0 }}</td>
                        <td class="px-6 py-3 text-sm text-orange-600 font-bold">{{ $damagedPerType[$type->id] ?? 0 }}</td>
                        <td class="px-6 py-3 text-sm">
                            @can('badges.stock.history')
                                <a href="{{ route('badges.stock.history', ['badge_type_id' => $type->id]) }}" class="text-[#0a5c36] font-bold hover:underline">عرض الحركة</a>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto mb-8" dir="rtl">
        <div class="px-6 py-4 font-black">أرصدة عهد الطاقم الحالية</div>
        <table class="w-full text-right">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-6 py-3 text-sm font-bold text-gray-600">صاحب العهدة</th>
                    @foreach ($types as $type)<th class="px-6 py-3 text-sm font-bold text-gray-600">{{ $type->name }}</th>@endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($teachers as $teacher)
                    <tr>
                        <td class="px-6 py-3 text-sm font-bold">{{ $teacher->name }}</td>
                        @foreach ($types as $type)
                            <td class="px-6 py-3 text-sm">{{ $teacherBalances[$teacher->id][$type->id] ?? 0 }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ 1 + $types->count() }}" class="px-6 py-8 text-center text-gray-400 text-sm">لا أرصدة معلمين بعد</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="grid lg:grid-cols-2 gap-6" dir="rtl">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto">
            <div class="px-6 py-4 font-black">الأوسمة الناقصة المفتوحة (أحدث 50)</div>
            <table class="w-full text-right">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-sm font-bold text-gray-600">الطالب</th>
                        <th class="px-6 py-3 text-sm font-bold text-gray-600">النوع</th>
                        <th class="px-6 py-3 text-sm font-bold text-gray-600">الناقص</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($openShortages as $s)
                        <tr>
                            <td class="px-6 py-3 text-sm">{{ $s->student->name }}</td>
                            <td class="px-6 py-3 text-sm">{{ $s->badgeType->name }}</td>
                            <td class="px-6 py-3 text-sm font-black text-red-600">{{ $s->shortage_quantity }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-8 text-center text-gray-400 text-sm">لا عجز مفتوح</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto">
            <div class="px-6 py-4 font-black">الأوسمة المفقودة (أحدث 50)</div>
            <table class="w-full text-right">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-sm font-bold text-gray-600">الطالب</th>
                        <th class="px-6 py-3 text-sm font-bold text-gray-600">النوع</th>
                        <th class="px-6 py-3 text-sm font-bold text-gray-600">الحلقة</th>
                        <th class="px-6 py-3 text-sm font-bold text-gray-600">المفقود</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($missingItems as $item)
                        <tr>
                            <td class="px-6 py-3 text-sm">{{ $item->student->name }}</td>
                            <td class="px-6 py-3 text-sm">{{ $item->badgeType->name ?? '—' }}</td>
                            <td class="px-6 py-3 text-sm">{{ $item->round->circle->name }}</td>
                            <td class="px-6 py-3 text-sm font-black text-red-600">{{ $item->distributed_quantity - $item->collected_quantity }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-gray-400 text-sm">لا أوسمة مفقودة مسجلة</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.markaz-layout>
