<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 text-white shadow-xl mb-8">
        <h1 class="text-2xl font-black">تسليم أوسمة لمعلم</h1>
        <p class="text-emerald-100/80 text-sm">تُخصم الكمية من رصيد الإدارة وتُضاف لعهدة المعلم فورًا</p>
    </div>

    <form method="POST" action="{{ route('badges.handovers.store') }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 max-w-2xl" dir="rtl">
        @csrf
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">المعلم *</label>
                <select name="teacher_id" required class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    <option value="">— اختر المعلم —</option>
                    @foreach ($teachers as $t)<option value="{{ $t->id }}" @selected(old('teacher_id') == $t->id)>{{ $t->name }}</option>@endforeach
                </select>
                <x-input-error :messages="$errors->get('teacher_id')" class="mt-2" />
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">نوع الوسام *</label>
                <select name="badge_type_id" required class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    <option value="">— اختر النوع —</option>
                    @foreach ($types as $type)<option value="{{ $type->id }}" @selected(old('badge_type_id') == $type->id)>{{ $type->name }} (رصيد الإدارة: {{ $adminBalances[$type->id] ?? 0 }})</option>@endforeach
                </select>
                <x-input-error :messages="$errors->get('badge_type_id')" class="mt-2" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">الكمية *</label>
                    <input type="number" name="quantity" min="1" value="{{ old('quantity') }}" required class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
                    <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">تاريخ التسليم *</label>
                    <input type="date" name="handover_date" value="{{ old('handover_date', date('Y-m-d')) }}" required class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
                    <x-input-error :messages="$errors->get('handover_date')" class="mt-2" />
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">ملاحظات</label>
                <input type="text" name="notes" value="{{ old('notes') }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
            </div>
            <div class="flex gap-3">
                <button class="px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-2xl active:scale-95">تنفيذ التسليم</button>
                <a href="{{ route('badges.handovers.index') }}" class="px-6 py-3 bg-gray-100 font-bold rounded-2xl">إلغاء</a>
            </div>
        </div>
    </form>
</x-layouts.markaz-layout>
