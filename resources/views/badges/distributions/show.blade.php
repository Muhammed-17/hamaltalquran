<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 text-white shadow-xl mb-8">
        <h1 class="text-2xl font-black">توزيع #{{ $distribution->id }} — {{ $distribution->badgeType->name }} × {{ $distribution->quantity }}</h1>
        <p class="text-emerald-100/80 text-sm">للطالب {{ $distribution->student->name }} — {{ $distribution->entitlement->circle->name }} — {{ $distribution->distribution_date->format('Y-m-d') }}</p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4" dir="rtl">
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">المعلم الموزّع</div><div class="text-lg font-black">{{ $distribution->teacher->name }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">الحالة</div><div class="text-lg font-black">{{ $distribution->status === 'active' ? 'نشط' : 'ملغي' }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">المحصّل منه فعليًا</div><div class="text-lg font-black">{{ $collected }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">مرتبط بعجز؟</div><div class="text-lg font-black">{{ $distribution->shortage_id ? 'نعم — استكمال عجز' : 'لا' }}</div></div>
    </div>

    <div class="mt-6 flex gap-3" dir="rtl">
        <a href="{{ route('badges.entitlements.show', $distribution->entitlement) }}" class="px-5 py-2.5 bg-gray-100 rounded-xl font-bold text-sm">فتح الاستحقاق</a>
        @can('cancel', $distribution)
            @if ($distribution->status === 'active')
                <form method="POST" action="{{ route('badges.distributions.cancel', $distribution) }}" onsubmit="return confirm('إلغاء هذا التوزيع؟')">
                    @csrf
                    <button class="px-5 py-2.5 bg-red-50 text-red-600 rounded-xl font-bold text-sm">إلغاء التوزيع</button>
                </form>
            @endif
        @endcan
    </div>
</x-layouts.markaz-layout>
