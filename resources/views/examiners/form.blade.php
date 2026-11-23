@php
$examiner = $examiner ?? null;
@endphp

<div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 space-y-6">
    <div class="flex items-center gap-3 mb-6 border-b border-gray-50 pb-4">
        <div class="p-2 bg-emerald-50 rounded-xl text-[#0a5c36]">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-800">بيانات المختبر</h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="space-y-2">
            <label for="name" class="block text-sm font-bold text-gray-700">الاسم <span class="text-red-500">*</span></label>
            <input id="name" type="text" name="name" autocomplete="off"
                value="{{ old('name', $examiner->user->name ?? '') }}"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label for="email" class="block text-sm font-bold text-gray-700">البريد الإلكتروني <span class="text-red-500">*</span></label>
            <input id="email" type="email" name="email" autocomplete="off"
                value="{{ old('email', $examiner->user->email ?? '') }}"
                placeholder="example@examiner.com"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
            @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label for="password" class="block text-sm font-bold text-gray-700">كلمة المرور
                @if(!isset($examiner)) <span class="text-red-500">*</span> @endif
            </label>
            <input id="password" type="password" name="password" autocomplete="new-password"
                placeholder="{{ isset($examiner) ? 'اتركه فارغاً لعدم التغيير' : '' }}"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
            @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label for="status" class="block text-sm font-bold text-gray-700">الحالة <span class="text-red-500">*</span></label>
            <select id="status" name="status" autocomplete="off"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all appearance-none">
                @php $statusOld = old('status', $examiner->user->status ?? 'active'); @endphp
                <option value="active" {{ $statusOld == 'active' ? 'selected' : '' }}>نشط</option>
                <option value="inactive" {{ $statusOld == 'inactive' ? 'selected' : '' }}>غير نشط</option>
            </select>
            @error('status') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2 md:col-span-2">
            <label for="center_id" class="block text-sm font-bold text-gray-700">المركز</label>
            <x-searchable-select
                name="center_id"
                :options="$centers->map(fn($center) => ['value' => $center->id, 'label' => $center->name])->values()"
                placeholder="-- اختر المركز --"
                searchPlaceholder="ابحث عن مركز..."
                :defaultValue="old('center_id', $examiner->center_id ?? '')" />
            @error('center_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label for="phone" class="block text-sm font-bold text-gray-700">رقم الهاتف</label>
            <input id="phone" type="text" name="phone" autocomplete="off" value="{{ old('phone', $examiner->phone ?? '') }}"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
            @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label for="secondary_phone" class="block text-sm font-bold text-gray-700">رقم الهاتف الإضافي</label>
            <input id="secondary_phone" type="text" name="secondary_phone" autocomplete="off"
                value="{{ old('secondary_phone', $examiner->secondary_phone ?? '') }}"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
            @error('secondary_phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2 md:col-span-2">
            <label for="address" class="block text-sm font-bold text-gray-700">العنوان</label>
            <input id="address" type="text" name="address" autocomplete="off" value="{{ old('address', $examiner->address ?? '') }}"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
            @error('address') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2 md:col-span-2">
            <label for="notes" class="block text-sm font-bold text-gray-700">ملاحظات</label>
            <textarea id="notes" name="notes" rows="3"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">{{ old('notes', $examiner->notes ?? '') }}</textarea>
            @error('notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</div>