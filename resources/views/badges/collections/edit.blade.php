<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 text-white shadow-xl mb-8">
        <h1 class="text-2xl font-black">تعديل جولة التحصيل — {{ $round->circle->name }}</h1>
    </div>

    <form method="POST" action="{{ route('badges.collections.update', $round) }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 max-w-2xl" dir="rtl">
        @csrf @method('PUT')
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">تاريخ التحصيل *</label>
                <input type="date" name="collection_date" value="{{ old('collection_date', $round->collection_date->format('Y-m-d')) }}" required class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
                <x-input-error :messages="$errors->get('collection_date')" class="mt-2" />
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">ملاحظات</label>
                <input type="text" name="notes" value="{{ old('notes', $round->notes) }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
            </div>
            <div class="flex gap-3">
                <button class="px-6 py-3 bg-emerald-600 text-white font-bold rounded-2xl active:scale-95">حفظ</button>
                <a href="{{ route('badges.collections.show', $round) }}" class="px-6 py-3 bg-gray-100 font-bold rounded-2xl">رجوع</a>
            </div>
        </div>
    </form>
</x-layouts.markaz-layout>
