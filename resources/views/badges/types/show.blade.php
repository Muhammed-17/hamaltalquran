<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 text-white shadow-xl mb-8 flex items-center gap-4">
        <div>
            <h1 class="text-2xl font-black">{{ $type->name }}</h1>
            <p class="text-emerald-100/80 text-sm">{{ $type->description }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4" dir="rtl">
        <div class="bg-white rounded-2xl border shadow-sm p-5">
            <div class="text-xs font-bold text-gray-400 mb-1">رصيد الإدارة</div>
            <div class="text-2xl font-black">{{ $adminBalance }}</div>
        </div>
        <div class="bg-white rounded-2xl border shadow-sm p-5">
            <div class="text-xs font-bold text-gray-400 mb-1">الحالة</div>
            <div class="text-2xl font-black">{{ $type->is_active ? 'مفعّل' : 'معطّل' }}</div>
        </div>
    </div>

    <div class="mt-6 flex gap-3" dir="rtl">
        @if (auth()->user()->hasRole('admin'))
            <a href="{{ route('badges.stock.history', ['badge_type_id' => $type->id]) }}" class="px-5 py-2.5 bg-gray-100 rounded-xl font-bold text-sm">سجل حركة هذا النوع</a>
        @endif
        @if (auth()->user()->hasRole('admin'))
            <a href="{{ route('badges.types.edit', $type) }}" class="px-5 py-2.5 bg-emerald-600 text-white rounded-xl font-bold text-sm">تعديل</a>
        @endif
    </div>
</x-layouts.markaz-layout>
