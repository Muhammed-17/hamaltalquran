{{-- resources/views/group_session_plans/form.blade.php --}}
@php
$isEdit = isset($session);
$pageTitle = $isEdit ? 'تعديل جلسة' : 'جلسة جديدة';
$title = $isEdit ? 'تعديل جلسة المجموعة' : 'إضافة جلسة جديدة';
$action = $isEdit ? route('group-session-plans.update', $session) : route('group-session-plans.store');
$method = $isEdit ? 'PUT' : 'POST';
$btnText = $isEdit ? 'حفظ التعديلات' : 'إضافة الجلسة';
$icon = $isEdit ? 'fa-edit' : 'fa-plus-circle';
@endphp

<x-layouts.markaz-layout>
    <x-slot name="title">{{ $pageTitle }}</x-slot>

    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-xl shadow-sm">
            <div class="px-6 py-5 border-b border-gray-100">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                        <i class="fas {{ $icon }} text-blue-500"></i>
                    </div>
                    {{ $title }}
                </h2>
            </div>

            <form action="{{ $action }}" method="POST" class="p-6">
                @csrf
                @if($isEdit) @method($method) @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    {{-- الحلقة (الدائرة) --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">الحلقة <span class="text-red-500">*</span></label>
                        <x-searchable-select
                            name="circle_id"
                            :options="collect($circles ?? [])->map(fn($circle) => ['value' => $circle->id, 'label' => $circle->name])->values()"
                            placeholder="اختر الحلقة"
                            searchPlaceholder="ابحث عن حلقة..."
                            :defaultValue="old('circle_id', $session->circle_id ?? request('circle_id', ''))" />
                        @error('circle_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- اسم الجلسة --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">اسم الجلسة <span class="text-red-500">*</span></label>
                        <input type="text" name="session_name" value="{{ old('session_name', $session->session_name ?? '') }}" placeholder="مثال: حفظ سورة البقرة" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#0a5c36] outline-none @error('session_name') border-red-300 @enderror" required>
                        @error('session_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- وقت البدء --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">وقت البدء <span class="text-red-500">*</span></label>
                        <input type="time" name="start_time"
                            value="{{ old('start_time', isset($session) ? \Carbon\Carbon::parse($session->start_time)->format('H:i') : '') }}"
                            class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#0a5c36] outline-none @error('start_time') border-red-300 @enderror"
                            required>
                        @error('start_time')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- وقت الانتهاء --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">وقت الانتهاء <span class="text-red-500">*</span></label>
                        <input type="time" name="end_time"
                            value="{{ old('end_time', isset($session) ? \Carbon\Carbon::parse($session->end_time)->format('H:i') : '') }}"
                            class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#0a5c36] outline-none @error('end_time') border-red-300 @enderror"
                            required>
                        @error('end_time')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- المحتوى المخطط --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">الخطة المقترحة</label>
                        <textarea name="planned_content" rows="3" placeholder="ما هو الخطة المقترحة ..." class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#0a5c36] outline-none @error('planned_content') border-red-300 @enderror">{{ old('planned_content', $session->planned_content ?? '') }}</textarea>
                        @error('planned_content')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- المحتوى المنجز --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">ما تم إنجازه</label>
                        <textarea name="completed_content" rows="3" placeholder="ما تم إنجازه فعلياً في الجلسة (يُملأ بعد الجلسة)..." class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#0a5c36] outline-none @error('completed_content') border-red-300 @enderror">{{ old('completed_content', $session->completed_content ?? '') }}</textarea>
                        @error('completed_content')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- ملاحظات --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">ملاحظات</label>
                        <textarea name="notes" rows="2" placeholder="أي ملاحظات إضافية..." class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#0a5c36] outline-none @error('notes') border-red-300 @enderror">{{ old('notes', $session->notes ?? '') }}</textarea>
                        @error('notes')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="flex justify-between items-center mt-8 pt-5 border-t border-gray-100">
                    <a href="{{ route('group-session-plans.index') }}" class="text-gray-500 hover:text-gray-700 text-sm flex items-center gap-2">
                        <i class="fas fa-arrow-right"></i> رجوع
                    </a>
                    <button type="submit" class="bg-[#0a5c36] hover:bg-[#0d7a48] text-white px-6 py-2.5 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                        <i class="fas fa-save"></i> {{ $btnText }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.markaz-layout>