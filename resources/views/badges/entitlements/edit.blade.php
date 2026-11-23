<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 text-white shadow-xl mb-8">
        <h1 class="text-2xl font-black">تعديل الاستحقاق — {{ $entitlement->student->name }} ({{ $entitlement->badgeType->name }})</h1>
    </div>

    <form method="POST" action="{{ route('badges.entitlements.update', $entitlement) }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 max-w-2xl" dir="rtl">
        @csrf @method('PUT')
        <div class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">الكمية المستحقة *</label>
                    <input type="number" name="quantity" min="1" value="{{ old('quantity', $entitlement->quantity) }}" required class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
                    <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">تاريخ الاستحقاق *</label>
                    <input type="date" name="entitlement_date" value="{{ old('entitlement_date', $entitlement->entitlement_date->format('Y-m-d')) }}" required class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
                    <x-input-error :messages="$errors->get('entitlement_date')" class="mt-2" />
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">ملاحظات</label>
                <input type="text" name="notes" value="{{ old('notes', $entitlement->notes) }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
            </div>
            <div class="flex gap-3">
                <button class="px-6 py-3 bg-emerald-600 text-white font-bold rounded-2xl active:scale-95">حفظ التعديل</button>
                <a href="{{ route('badges.entitlements.index') }}" class="px-6 py-3 bg-gray-100 font-bold rounded-2xl">رجوع</a>
            </div>
        </div>
    </form>
</x-layouts.markaz-layout>
