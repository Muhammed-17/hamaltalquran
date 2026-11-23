<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#7a1f1f] rounded-3xl p-6 text-white shadow-xl mb-8">
        <h1 class="text-2xl font-black">عجز وسام {{ $shortage->badgeType->name }} — {{ $shortage->student->name }}</h1>
        <p class="text-red-100/80 text-sm">{{ $shortage->entitlement->circle->name }} — المعلم {{ $shortage->teacher->name }} — استحقاق {{ $shortage->entitlement->entitlement_date->format('Y-m-d') }}</p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4" dir="rtl">
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">المستحق</div><div class="text-2xl font-black">{{ $shortage->required_quantity }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">المسلّم</div><div class="text-2xl font-black text-emerald-700">{{ $shortage->delivered_quantity }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">الناقص</div><div class="text-2xl font-black text-red-600">{{ $shortage->shortage_quantity }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">رصيد الموزّع الحالي</div><div class="text-2xl font-black">{{ $teacherBalance }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">حالة العجز</div><div class="text-xl font-black">{{ $shortage->status->label() }}</div></div>
    </div>

    <div class="grid lg:grid-cols-5 gap-6 mt-6" dir="rtl">
        <div class="lg:col-span-3 bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto">
            <div class="px-6 py-4 font-black">تاريخ التسليمات على هذا الاستحقاق</div>
            <table class="w-full text-right">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-sm font-bold text-gray-600">الكمية</th>
                        <th class="px-6 py-3 text-sm font-bold text-gray-600">التاريخ</th>
                        <th class="px-6 py-3 text-sm font-bold text-gray-600">الحالة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($shortage->entitlement->distributions as $d)
                        <tr>
                            <td class="px-6 py-3 text-sm font-black">{{ $d->quantity }}</td>
                            <td class="px-6 py-3 text-sm">{{ $d->distribution_date->format('Y-m-d') }}</td>
                            <td class="px-6 py-3 text-sm">{{ $d->status === 'active' ? 'نشط' : 'ملغي' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-8 text-center text-gray-400 text-sm">لا تسليمات بعد</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-6 py-4">
                <a href="{{ route('badges.entitlements.show', $shortage->entitlement) }}" class="text-[#0a5c36] font-bold text-sm hover:underline">فتح الاستحقاق كاملًا</a>
            </div>
        </div>

        @if ($shortage->isOpen())
            @can('resolve', $shortage)
                <form method="POST" action="{{ route('badges.shortages.resolve', $shortage) }}" class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    @csrf
                    <h2 class="font-black text-lg mb-4">استكمال التسليم</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 mb-1.5">الكمية (الناقص {{ $shortage->shortage_quantity }} — رصيد الموزّع {{ $teacherBalance }})</label>
                            <input type="number" name="quantity" min="1" max="{{ $shortage->shortage_quantity }}" value="{{ min($shortage->shortage_quantity, max($teacherBalance, 0)) }}" required class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
                            <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 mb-1.5">تاريخ التسليم</label>
                            <input type="date" name="distribution_date" value="{{ date('Y-m-d') }}" required class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 mb-1.5">ملاحظات</label>
                            <input type="text" name="notes" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
                        </div>
                        <button class="px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-2xl active:scale-95 w-full">تسليم الآن</button>
                    </div>
                </form>
            @endcan
        @endif
    </div>
</x-layouts.markaz-layout>
