<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 text-white shadow-xl mb-8">
        <h1 class="text-2xl font-black">إنشاء جولة تحصيل أوسمة</h1>
        <p class="text-emerald-100/80 text-sm">ستُبنى الجولة كمسودة تشمل كل طلاب الحلقة وتوزيعاتهم غير المحصلة بالكامل</p>
    </div>

    <form method="POST" action="{{ route('badges.collections.store') }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 max-w-2xl" dir="rtl">
        @csrf
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">الحلقة *</label>
                <select name="circle_id" required class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    <option value="">— اختر الحلقة —</option>
                    @foreach ($circles as $c)<option value="{{ $c->id }}" @selected(old('circle_id') == $c->id)>{{ $c->name }}</option>@endforeach
                </select>
                <x-input-error :messages="$errors->get('circle_id')" class="mt-2" />
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">المعلم المسؤول عن التحصيل *</label>
                <select name="teacher_id" required class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    <option value="">— اختر المعلم —</option>
                    @foreach ($teachers as $t)<option value="{{ $t->id }}" @selected(old('teacher_id') == $t->id)>{{ $t->name }}</option>@endforeach
                </select>
                <x-input-error :messages="$errors->get('teacher_id')" class="mt-2" />
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">تاريخ التحصيل *</label>
                <input type="date" name="collection_date" value="{{ old('collection_date', date('Y-m-d')) }}" required class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
                <x-input-error :messages="$errors->get('collection_date')" class="mt-2" />
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">ملاحظات</label>
                <input type="text" name="notes" value="{{ old('notes') }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
            </div>
            <div class="flex gap-3">
                <button class="px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-2xl active:scale-95">إنشاء الجولة</button>
                <a href="{{ route('badges.collections.index') }}" class="px-6 py-3 bg-gray-100 font-bold rounded-2xl">إلغاء</a>
            </div>
        </div>
    </form>
</x-layouts.markaz-layout>
