<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden shadow-xl mb-8">
        <h1 class="text-3xl font-black mb-2">لوحة تحكم الأوسمة</h1>
        <p class="text-emerald-100/80 text-sm font-medium">نظرة شاملة على المخزون والتسليم والتوزيع والتحصيل</p>
        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8" dir="rtl">
        @foreach ([
            ['أنواع الأوسمة', $stats['types_count'], 'text-gray-800'],
            ['مخزون الإدارة الحالي', $stats['admin_stock_total'], 'text-emerald-700'],
            ['إجمالي المسلّم للمعلمين', $stats['handed_over_total'], 'text-gray-800'],
            ['إجمالي الموزّع للطلاب', $stats['distributed_total'], 'text-gray-800'],
            ['استحقاقات مفتوحة', $stats['entitlements_open'], 'text-amber-600'],
            ['أوسمة ناقصة (عدد السجلات)', $stats['shortage_open_count'], 'text-red-600'],
            ['كمية الأوسمة الناقصة', $stats['shortage_open_qty'], 'text-red-600'],
            ['المحصّل فعليًا / المفقود', $stats['collected_total'] . ' / ' . $stats['missing_total'], 'text-gray-800'],
        ] as [$label, $value, $cls])
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="text-xs font-bold text-gray-400 mb-1">{{ $label }}</div>
                <div class="text-2xl font-black {{ $cls }}">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-2 gap-6" dir="rtl">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <h2 class="font-black text-lg mb-4">مخزون الإدارة حسب النوع</h2>
            <table class="w-full text-right">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-sm font-bold text-gray-600">النوع</th>
                        <th class="px-4 py-3 text-sm font-bold text-gray-600">الرصيد</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($types as $type)
                        <tr>
                            <td class="px-4 py-3 text-sm">{{ $type->name }} @unless($type->is_active) <span class="text-xs text-red-500">(معطّل)</span> @endunless</td>
                            <td class="px-4 py-3 text-sm font-bold">{{ $adminBalances[$type->id] ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-4 py-6 text-center text-sm text-gray-400">لا توجد أنواع أوسمة بعد</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="space-y-6">
            @if (!empty($myBalances))
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <h2 class="font-black text-lg mb-4">رصيدي الحالي</h2>
                    <ul class="space-y-2">
                        @foreach ($myBalances as $typeId => $balance)
                            <li class="flex justify-between text-sm">
                                <span>{{ $types->firstWhere('id', $typeId)?->name }}</span>
                                <span class="font-bold">{{ $balance }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($latestLogs->isNotEmpty())
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <h2 class="font-black text-lg mb-4">أحدث العمليات</h2>
                    <ul class="space-y-3">
                        @foreach ($latestLogs as $log)
                            <li class="text-sm flex justify-between gap-4">
                                <span>{{ $log->action }} — {{ $log->entity_type }} #{{ $log->entity_id }}</span>
                                <span class="text-gray-400 text-xs whitespace-nowrap">{{ $log->created_at?->format('Y-m-d H:i') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-layouts.markaz-layout>
