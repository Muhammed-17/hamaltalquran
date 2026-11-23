<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 text-white shadow-xl mb-8">
        <h1 class="text-2xl font-black">استحقاق {{ $entitlement->badgeType->name }} — {{ $entitlement->student->name }}</h1>
        <p class="text-emerald-100/80 text-sm">{{ $entitlement->circle->name }} — المعلم {{ $entitlement->teacher->name }} — {{ $entitlement->entitlement_date->format('Y-m-d') }}</p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4" dir="rtl">
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">المستحق</div><div class="text-2xl font-black">{{ $entitlement->quantity }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">المسلّم فعليًا</div><div class="text-2xl font-black text-emerald-700">{{ $delivered }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">المتبقي</div><div class="text-2xl font-black text-amber-600">{{ $remaining }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">رصيد الموزّع من النوع</div><div class="text-2xl font-black">{{ $teacherBalance }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">الحالة</div><div class="text-xl font-black">{{ $entitlement->status->label() }}</div></div>
    </div>

    @if ($entitlement->shortage)
        <div class="bg-red-50 border border-red-100 rounded-2xl p-5 mt-6" dir="rtl">
            <span class="font-black text-red-700">يوجد عجز مرتبط بهذا الاستحقاق:</span>
            <span class="text-sm">المطلوب {{ $entitlement->shortage->required_quantity }} — المسلّم {{ $entitlement->shortage->delivered_quantity }} — الناقص {{ $entitlement->shortage->shortage_quantity }} ({{ $entitlement->shortage->status->label() }})</span>
            <a href="{{ route('badges.shortages.show', $entitlement->shortage) }}" class="text-red-700 font-bold underline text-sm">فتح العجز</a>
        </div>
    @endif

    <div class="grid lg:grid-cols-5 gap-6 mt-6" dir="rtl">
        <div class="lg:col-span-3 bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto">
            <div class="px-6 py-4 font-black">سجل التسليمات الفعلية</div>
            <table class="w-full text-right">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-sm font-bold text-gray-600">الكمية</th>
                        <th class="px-6 py-3 text-sm font-bold text-gray-600">التاريخ</th>
                        <th class="px-6 py-3 text-sm font-bold text-gray-600">الحالة</th>
                        <th class="px-6 py-3 text-sm font-bold text-gray-600">بواسطة</th>
                        <th class="px-6 py-3 text-sm font-bold text-gray-600"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($entitlement->distributions as $d)
                        <tr>
                            <td class="px-6 py-3 text-sm font-black">{{ $d->quantity }}</td>
                            <td class="px-6 py-3 text-sm">{{ $d->distribution_date->format('Y-m-d') }}</td>
                            <td class="px-6 py-3 text-sm">{{ $d->status === 'active' ? 'نشط' : 'ملغي' }}@if ($d->shortage_id) <span class="text-xs text-amber-600">(استكمال عجز)</span>@endif</td>
                            <td class="px-6 py-3 text-sm">{{ $d->creator->name }}</td>
                            <td class="px-6 py-3 text-sm">
                                @can('cancel', $d)
                                    @if ($d->status === 'active')
                                        <form method="POST" action="{{ route('badges.distributions.cancel', $d) }}" onsubmit="return confirm('إلغاء هذا التوزيع؟ ستعود الكمية لعهدة المعلم.')">
                                            @csrf
                                            <button class="text-red-600 font-bold hover:underline">إلغاء</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-gray-400 text-sm">لا تسليمات بعد</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="lg:col-span-2 space-y-6">
            @if ($entitlement->status->value !== 'cancelled' && $remaining > 0)
                @can('create', [\App\Models\Badges\BadgeDistribution::class, $entitlement])
                    <form method="POST" action="{{ route('badges.distributions.store') }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                        @csrf
                        <input type="hidden" name="entitlement_id" value="{{ $entitlement->id }}">
                        <h2 class="font-black text-lg mb-4">تسجيل تسليم فعلي</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-400 mb-1.5">الكمية (المتبقي {{ $remaining }} — رصيد الموزّع {{ $teacherBalance }})</label>
                                <input type="number" name="quantity" min="1" max="{{ $remaining }}" value="{{ min($remaining, max($teacherBalance, 0)) }}" required class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
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

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <h2 class="font-black text-lg mb-4">إجراءات</h2>
                <div class="flex flex-wrap gap-3">
                    @can('update', $entitlement)
                        <a href="{{ route('badges.entitlements.edit', $entitlement) }}" class="px-5 py-2.5 bg-gray-100 rounded-xl font-bold text-sm">تعديل الاستحقاق</a>
                    @endcan
                    @can('cancel', $entitlement)
                        @if ($entitlement->status->value !== 'cancelled')
                            <form method="POST" action="{{ route('badges.entitlements.cancel', $entitlement) }}" onsubmit="return confirm('إلغاء هذا الاستحقاق؟')">
                                @csrf
                                <button class="px-5 py-2.5 bg-red-50 text-red-600 rounded-xl font-bold text-sm">إلغاء الاستحقاق</button>
                            </form>
                        @endif
                    @endcan
                </div>
                @if ($entitlement->notes)<p class="text-sm text-gray-500 mt-4">{{ $entitlement->notes }}</p>@endif
            </div>
        </div>
    </div>
</x-layouts.markaz-layout>
