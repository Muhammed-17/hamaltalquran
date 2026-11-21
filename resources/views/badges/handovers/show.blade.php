<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 text-white shadow-xl mb-8">
        <h1 class="text-2xl font-black">تسليم #{{ $handover->id }} — {{ $handover->badgeType->name }}</h1>
        <p class="text-emerald-100/80 text-sm">للمعلم {{ $handover->teacher->name }} بتاريخ {{ $handover->handover_date->format('Y-m-d') }}</p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4" dir="rtl">
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">الكمية المسلّمة</div><div class="text-2xl font-black">{{ $handover->quantity }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">الحالة</div><div class="text-2xl font-black">{{ $handover->status === 'active' ? 'نشط' : 'ملغي' }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">رصيد المعلم الحالي من النوع</div><div class="text-2xl font-black">{{ $teacherBalance }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">رصيد الإدارة من النوع</div><div class="text-2xl font-black">{{ $adminBalance }}</div></div>
    </div>

    <div class="bg-white rounded-2xl border shadow-sm p-6 mt-6" dir="rtl">
        <p class="text-sm text-gray-500">سجّله: {{ $handover->creator->name }} — {{ $handover->created_at->format('Y-m-d H:i') }}</p>
        @if ($handover->notes)<p class="text-sm mt-2">{{ $handover->notes }}</p>@endif
        @if ($handover->status === 'cancelled')<p class="text-sm text-red-600 mt-2">أُلغي بتاريخ {{ $handover->cancelled_at?->format('Y-m-d H:i') }}</p>@endif
    </div>
</x-layouts.markaz-layout>
