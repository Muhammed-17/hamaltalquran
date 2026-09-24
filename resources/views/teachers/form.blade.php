@php
$isEdit = isset($teacher) && $teacher->exists;
$currentRoles = old('roles', $isEdit ? $teacher->user->roles->pluck('name')->toArray() : []);
@endphp
<div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 space-y-6">
    <div class="flex items-center gap-3 mb-8 border-b border-gray-50 pb-4">
        <div class="p-2 bg-emerald-50 rounded-xl text-[#0a5c36]">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-800">بيانات المعلم</h2>
    </div>

    {{-- الاسم --}}
    <div class="space-y-2">
        <label class="block text-sm font-bold text-gray-700">الاسم <span class="text-red-500">*</span></label>
        <input type="text" name="name"
            value="{{ old('name', $teacher->name ?? '') }}"
            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
        @error('name')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- البريد الإلكتروني --}}
    <div class="space-y-2">
        <label class="block text-sm font-bold text-gray-700">البريد الإلكتروني <span class="text-red-500">*</span></label>
        <input type="email" name="email"
            value="{{ old('email', $teacher->user->email ?? '') }}"
            placeholder="example@teacher.com"
            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all ltr">

        @error('email')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- كلمة المرور --}}
    <div class="space-y-2" x-data="{ show: false }">
        <label class="block text-sm font-bold text-gray-700">
            كلمة المرور
            @if($isEdit)
            <span class="text-gray-400 font-normal">(اتركها فارغة إذا لم ترغب في التغيير)</span>
            @else
            <span class="text-red-500">*</span>
            @endif
        </label>
        <div class="relative">
            <div class="relative">
                <input x-bind:type="show ? 'text' : 'password'" name="password"
                    placeholder="{{ $isEdit ? 'اتركها فارغة إذا لم ترغب في التغيير' : 'أدخل كلمة المرور ' }}"
                    class="w-full px-4 py-3 pl-10 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all ltr">

                <button type="button" @click="show = !show"
                    class="absolute inset-y-0 left-0 flex items-center px-3 text-gray-400 hover:text-gray-600">
                    <span x-text="show ? '🙈' : '👁'"></span>
                </button>
            </div>
            @error('password')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>

    {{-- ✅ إضافة هذا هنا: كلمة المرور الحالية (فقط في وضع التعديل وإذا لم يكن admin أو general_manager) --}}
    @if($isEdit && !auth()->user()->hasRole(['admin', 'general_manager','manager']))
    <div class="space-y-2" x-data="{ showCurrent: false }">
        <label class="block text-sm font-bold text-gray-700">
            كلمة المرور الحالية
            <span class="text-gray-400 font-normal">(مطلوبة لتغيير كلمة المرور)</span>
        </label>
        <div class="relative">
            <input x-bind:type="showCurrent ? 'text' : 'password'" name="current_password"
                placeholder="أدخل كلمة المرور الحالية"
                class="w-full px-4 py-3 pl-10 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all ltr">

            <button type="button" @click="showCurrent = !showCurrent"
                class="absolute inset-y-0 left-0 flex items-center px-3 text-gray-400 hover:text-gray-600">
                <span x-text="showCurrent ? '🙈' : '👁'"></span>
            </button>
        </div>
        @error('current_password')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>
    @endif

    {{-- الفرع / المركز --}}
    <div class="space-y-2">
        <label class="block text-sm font-bold text-gray-700">الفرع <span class="text-red-500">*</span></label>

        @if(count($branches) === 1)
        <input type="hidden" name="branch_id" value="{{ $branches->first()->id }}">
        <div class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-2xl text-sm text-gray-600 font-bold">
            {{ $branches->first()->name }}
        </div>
        @else
        <x-searchable-select
            name="branch_id"
            :options="$branches->map(fn($branch) => ['value' => $branch->id, 'label' => $branch->name])->values()"
            placeholder="-- اختر الفرع --"
            searchPlaceholder="ابحث عن فرع..."
            :defaultValue="old('branch_id', $teacher->branch_id ?? '')" />
        @endif

        @error('branch_id')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- نوع المستخدم (الأدوار) --}}
    <div class="space-y-2">
        <label class="block text-sm font-bold text-gray-700">
            نوع المستخدم <span class="text-red-500">*</span>
        </label>
        <div class="grid grid-cols-2 gap-3 p-4 bg-gray-50 rounded-2xl border border-gray-100">
            @foreach($roles as $role)
            <label class="flex items-center gap-3 p-3 bg-white rounded-xl border border-gray-100 cursor-pointer hover:border-[#0a5c36]/50 transition-all">
                <input type="radio" name="roles[]" value="{{ $role->name }}"
                    {{ in_array($role->name, $currentRoles) ? 'checked' : '' }}
                    class="text-[#0a5c36] focus:ring-[#0a5c36]">
                <span class="text-sm font-bold text-gray-700">
                    {{ $role->display_name ?? $role->name }}
                </span>
            </label>
            @endforeach
        </div>
        @error('roles')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>
</div>