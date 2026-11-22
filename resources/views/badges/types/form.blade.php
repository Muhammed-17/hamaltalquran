<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6" dir="rtl">
    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-bold text-gray-400 mb-1.5">اسم الوسام *</label>
            <input type="text" name="name" value="{{ old('name', $type->name) }}" required
                class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-1 focus:ring-[#0a5c36]">
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs font-bold text-gray-400 mb-1.5">الوصف</label>
            <textarea name="description" rows="3"
                class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-1 focus:ring-[#0a5c36]">{{ old('description', $type->description) }}</textarea>
        </div>
        <label class="flex items-center gap-2 text-sm font-bold text-gray-600">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $type->is_active ?? true)) class="rounded">
            مفعّل
        </label>
    </div>
    <div class="mt-6 flex gap-3">
        <button type="submit" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-2xl transition-all active:scale-95">حفظ</button>
        <a href="{{ route('badges.types.index') }}" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-2xl">إلغاء</a>
    </div>
</div>
