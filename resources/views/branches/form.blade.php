@php
$isEdit = isset($branch) && $branch->exists;
@endphp

<div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 space-y-6">
    <div class="flex items-center gap-3 mb-6 border-b border-gray-50 pb-4">
        <div class="p-2 bg-emerald-50 rounded-xl text-[#0a5c36]">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-800">
            {{ $isEdit ? 'تعديل بيانات المقر' : 'بيانات المقر' }}
        </h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <div class="space-y-2">
            <label for="branch_name" class="block text-sm font-bold text-gray-700">اسم المقر <span class="text-red-500">*</span></label>
            <input id="branch_name" type="text" name="name" autocomplete="off" value="{{ old('name', $branch->name ?? '') }}"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label for="center_id" class="block text-sm font-bold text-gray-700">المركز <span class="text-red-500">*</span></label>
            <select id="center_id" name="center_id" autocomplete="off"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all appearance-none">
                <option value="">-- اختر المركز --</option>
                @foreach($centers ?? [] as $center)
                <option value="{{ $center->id }}"
                    {{ old('center_id', $branch->center_id ?? '') == $center->id ? 'selected' : '' }}>
                    {{ $center->name }}
                </option>
                @endforeach
            </select>
            @error('center_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label for="address" class="block text-sm font-bold text-gray-700">العنوان</label>
            <input id="address" type="text" name="address" autocomplete="off" value="{{ old('address', $branch->address ?? '') }}"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all"
                placeholder="مثال: شارع النصر، مدينة نصر">
            @error('address') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label for="established_at" class="block text-sm font-bold text-gray-700">تاريخ الإنشاء <span class="text-red-500">*</span></label>
            <input id="established_at" type="date" name="established_at" autocomplete="off"
                value="{{ old('established_at', isset($branch->established_at) ? $branch->established_at->format('Y-m-d') : '') }}"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
            @error('established_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- ═══════════════════════════════════════════════
             حقل المشرفين
        ════════════════════════════════════════════════ --}}
        <div class="md:col-span-2 space-y-2">
            <span class="block text-sm font-bold text-gray-700">المشرفون</span>

            @php
            $selectedSupervisorIds = old('supervisor_ids', ($branch->supervisors ?? collect())->pluck('id')->all());
            $selectedSupervisorIds = array_map('strval', $selectedSupervisorIds);
            @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 bg-gray-50 border border-gray-200 rounded-2xl p-4">
                @forelse($supervisors ?? [] as $supervisor)
                @php
                $roleName = $supervisor->user?->roles?->first()?->name ?? $supervisor->roles?->first()?->name ?? '';
                $roleLabel = match($roleName) {
                'admin' => 'المسؤول',
                'general_manager' => 'المدير العام',
                'manager' => 'مدير فرع',
                'supervisor' => 'مشرف',
                'teacher' => 'معلم',
                default => 'مشرف',
                };
                @endphp
                <label class="flex items-center gap-2 px-3 py-2 bg-white border border-gray-200 rounded-xl cursor-pointer hover:border-emerald-300 transition-all">
                    <input type="checkbox" name="supervisor_ids[]" value="{{ $supervisor->id }}"
                        class="w-4 h-4 text-[#0a5c36] border-gray-300 rounded focus:ring-emerald-200"
                        {{ in_array((string) $supervisor->id, $selectedSupervisorIds, true) ? 'checked' : '' }}>
                    <span class="text-sm text-gray-700">
                        {{ $supervisor->user?->name ?? $supervisor->name }} ({{ $roleLabel }})
                        @if($supervisor->branch)
                        <span class="text-xs text-gray-400">— {{ $supervisor->branch->name }}</span>
                        @endif
                    </span>
                </label>
                @empty
                <p class="text-sm text-red-500 col-span-2">
                    لا يوجد معلمون مؤهلون (مشرف / مدير فرع / مدير عام) لتعيينهم — يجب إضافة معلم بأحد هذه الأدوار أولاً قبل إنشاء الفرع.
                </p>
                @endforelse
            </div>
            @error('supervisor_ids') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            @error('supervisor_ids.*') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

    </div>
</div>