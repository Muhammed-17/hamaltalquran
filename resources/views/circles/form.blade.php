@php
$isEdit = isset($circle) && $circle->exists;
$canManageAll = auth()->user()->hasRole(['admin', 'general_manager']);

// خيارات الفروع لمكون البحث
$branchOptions = collect($branches ?? [])->map(fn($b) => [
    'value' => $b->id,
    'label' => $b->center ? "{$b->name} ({$b->center->name})" : $b->name,
])->values();

// خيارات المعلمين (نفس القائمة تُستخدم للمعلم الأساسي والمساعد)
$teacherOptions = collect($teachers ?? [])->map(fn($t) => [
'value' => $t->id,
'label' => $t->center ? "{$t->name} ({$t->center->name})" : $t->name,
])->values();

$selectedTeacherId = old('teacher_id', $circle->mainTeachers->first()?->id ?? '');
$selectedAssistantId = old('assistant_teacher_id', $circle->assistantTeachers->first()?->id ?? '');
@endphp

<div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 space-y-6">
    <div class="flex items-center gap-3 mb-6 border-b border-gray-50 pb-4">
        <div class="p-2 bg-emerald-50 rounded-xl text-[#0a5c36]">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-800">بيانات الحلقة</h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        @if($canManageAll)
        {{-- ═══════════════════════════════════════════════
                admin / general_manager — كل الحقول قابلة للتعديل
            ════════════════════════════════════════════════ --}}
        <div class="space-y-2">
            <label for="circle_name" class="block text-sm font-bold text-gray-700">اسم الحلقة <span class="text-red-500">*</span></label>
            <input id="circle_name" type="text" name="name" autocomplete="off" value="{{ old('name', $circle->name ?? '') }}"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label class="block text-sm font-bold text-gray-700">المقر <span class="text-red-500">*</span></label>
            <x-searchable-select
                name="branch_id"
                :options="$branchOptions"
                :default-value="old('branch_id', $circle->branch_id ?? '')"
                placeholder="-- اختر المقر --"
                search-placeholder="ابحث عن مقر..." />
            @error('branch_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label for="circle_url" class="block text-sm font-bold text-gray-700">رابط الحلقة</label>
            <input id="circle_url" type="url" name="url" autocomplete="off" value="{{ old('url', $circle->url ?? '') }}"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all"
                placeholder="https://chat.whatsapp.com/..." dir="ltr">
            @error('url') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label for="circle_type" class="block text-sm font-bold text-gray-700">نوع الحلقة <span class="text-red-500">*</span></label>
            <select id="circle_type" name="type" autocomplete="off"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all appearance-none">
                <option value="group" {{ old('type', $circle->type ?? '') == 'group' ? 'selected' : '' }}>جماعي</option>
                <option value="individual" {{ old('type', $circle->type ?? '') == 'individual' ? 'selected' : '' }}>فردي</option>
            </select>
            @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label for="circle_level" class="block text-sm font-bold text-gray-700">مستوى الحلقة <span class="text-red-500">*</span></label>
            <select id="circle_level" name="level" autocomplete="off"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all appearance-none">
                <option value="build" {{ old('level', $circle->level ?? '') == 'build' ? 'selected' : '' }}>بناء</option>
                <option value="mastery" {{ old('level', $circle->level ?? '') == 'mastery' ? 'selected' : '' }}>إتقان</option>
                <option value="creativity" {{ old('level', $circle->level ?? '') == 'creativity' ? 'selected' : '' }}>إبداع</option>
            </select>
            @error('level') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        @else
        {{-- ═══════════════════════════════════════════════
                مدير مقر / مشرف / معلم — المقر مقيد تلقائياً
            ════════════════════════════════════════════════ --}}

        {{-- ✅ FIX: branch_id دائماً موجود وصالح --}}
        @php
        $defaultBranchId = $circle->branch_id ?? ($branches->first()?->id ?? '');
        @endphp

        @if(!$defaultBranchId)
        <div class="md:col-span-2 bg-red-50 border border-red-200 p-4 rounded-2xl text-red-700 font-bold">
            لا يوجد مقر مرتبط بحسابك.
        </div>
        @else
        <input type="hidden" name="branch_id" value="{{ $defaultBranchId }}">
        @endif

        @if($isEdit)
        {{-- تعديل: الاسم/النوع/المستوى مقفولة، المقر ثابت --}}
        <input type="hidden" name="name" value="{{ $circle->name }}">
        <input type="hidden" name="type" value="{{ $circle->type }}">
        <input type="hidden" name="level" value="{{ $circle->level }}">

        <div class="md:col-span-2 bg-emerald-50 p-4 rounded-2xl text-emerald-800 font-bold border border-emerald-100">
            تعديل الحلقة: {{ $circle->name }} — المقر: {{ $circle->branch?->name ?? '—' }}
        </div>

        <div class="space-y-2 md:col-span-2">
            <label for="circle_url_edit" class="block text-sm font-bold text-gray-700">رابط الحلقة</label>
            <input id="circle_url_edit" type="url" name="url" autocomplete="off" value="{{ old('url', $circle->url ?? '') }}"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all"
                placeholder="https://chat.whatsapp.com/..." dir="ltr">
            @error('url') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        @else
        {{-- إنشاء: اسم/نوع/مستوى/رابط قابلة للتعبئة، المقر تلقائي --}}
        <div class="md:col-span-2 bg-blue-50 border border-blue-100 rounded-2xl px-4 py-3 text-sm text-blue-700 font-medium">
            المقر: {{ $branches->first()?->name ?? '—' }}
        </div>

        <div class="space-y-2">
            <label for="circle_name_mgr" class="block text-sm font-bold text-gray-700">اسم الحلقة <span class="text-red-500">*</span></label>
            <input id="circle_name_mgr" type="text" name="name" autocomplete="off" value="{{ old('name') }}"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label for="circle_url_mgr" class="block text-sm font-bold text-gray-700">رابط الحلقة</label>
            <input id="circle_url_mgr" type="url" name="url" autocomplete="off" value="{{ old('url') }}"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all"
                placeholder="https://chat.whatsapp.com/..." dir="ltr">
            @error('url') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label for="circle_type_mgr" class="block text-sm font-bold text-gray-700">نوع الحلقة <span class="text-red-500">*</span></label>
            <select id="circle_type_mgr" name="type" autocomplete="off"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all appearance-none">
                <option value="group">جماعي</option>
                <option value="individual">فردي</option>
            </select>
            @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-2">
            <label for="circle_level_mgr" class="block text-sm font-bold text-gray-700">مستوى الحلقة <span class="text-red-500">*</span></label>
            <select id="circle_level_mgr" name="level" autocomplete="off"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all appearance-none">
                <option value="build">بناء</option>
                <option value="mastery">إتقان</option>
                <option value="creativity">إبداع</option>
            </select>
            @error('level') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        @endif
        @endif

        {{-- المعلم الأساسي --}}
        <div class="space-y-2">
            <label class="block text-sm font-bold text-gray-700">المعلم الأساسي</label>
            <x-searchable-select
                name="teacher_id"
                :options="$teacherOptions"
                :default-value="$selectedTeacherId"
                placeholder="-- اختر المعلم --"
                search-placeholder="ابحث عن معلم..." />
            @error('teacher_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- المعلم المساعد --}}
        <div class="space-y-2">
            <label class="block text-sm font-bold text-gray-700">المعلم المساعد</label>
            <x-searchable-select
                name="assistant_teacher_id"
                :options="$teacherOptions"
                :default-value="$selectedAssistantId"
                placeholder="-- اختر المعلم المساعد --"
                search-placeholder="ابحث عن معلم..." />
            @error('assistant_teacher_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

    </div>
</div>