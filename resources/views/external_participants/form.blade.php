@php
$externalParticipant = $externalParticipant ?? null;
@endphp

<div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 space-y-6">
    <div class="flex items-center gap-3 mb-6 border-b border-gray-50 pb-4">
        <div class="p-2 bg-emerald-50 rounded-xl text-[#0a5c36]">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-800">بيانات المشارك الخارجي</h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="space-y-2 md:col-span-2">
            <label for="name" class="block text-sm font-bold text-gray-700">الاسم <span class="text-red-500">*</span></label>
            <input id="name" type="text" name="name" autocomplete="off" value="{{ old('name', $externalParticipant->name ?? '') }}"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label for="national_id" class="block text-sm font-bold text-gray-700">الرقم القومي</label>
            <input id="national_id" type="text" name="national_id" autocomplete="off"
                value="{{ old('national_id', $externalParticipant->national_id ?? '') }}"
                inputmode="numeric"
                pattern="\d{14}"
                maxlength="14"
                minlength="14"
                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 14)"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
            @error('national_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label for="gender" class="block text-sm font-bold text-gray-700">النوع</label>
            <select id="gender" name="gender" autocomplete="off"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all appearance-none">
                <option value="">-- غير محدد --</option>
                <option value="male" {{ old('gender', $externalParticipant->gender ?? '') == 'male' ? 'selected' : '' }}>ذكر</option>
                <option value="female" {{ old('gender', $externalParticipant->gender ?? '') == 'female' ? 'selected' : '' }}>أنثى</option>
            </select>
            @error('gender') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label for="phone" class="block text-sm font-bold text-gray-700">رقم الهاتف</label>
            <input id="phone" type="text" name="phone" autocomplete="off" value="{{ old('phone', $externalParticipant->phone ?? '') }}"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
            @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label for="secondary_phone" class="block text-sm font-bold text-gray-700">رقم الهاتف الإضافي</label>
            <input id="secondary_phone" type="text" name="secondary_phone" autocomplete="off"
                value="{{ old('secondary_phone', $externalParticipant->secondary_phone ?? '') }}"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
            @error('secondary_phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label for="date_of_birth" class="block text-sm font-bold text-gray-700">تاريخ الميلاد</label>
            <input id="date_of_birth" type="date" name="date_of_birth" autocomplete="off"
                value="{{ old('date_of_birth', optional($externalParticipant->date_of_birth ?? null)->format('Y-m-d')) }}"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
            @error('date_of_birth') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2 md:col-span-2">
            <label for="address" class="block text-sm font-bold text-gray-700">العنوان</label>
            <input id="address" type="text" name="address" autocomplete="off" value="{{ old('address', $externalParticipant->address ?? '') }}"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
            @error('address') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2 md:col-span-2">
            <label for="notes" class="block text-sm font-bold text-gray-700">ملاحظات</label>
            <textarea id="notes" name="notes" rows="3"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">{{ old('notes', $externalParticipant->notes ?? '') }}</textarea>
            @error('notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</div>