{{-- resources/views/student_construction_details/form.blade.php --}}
@php
$isEdit = isset($detail);
$pageTitle = $isEdit ? 'تعديل خطة بناء' : 'خطة بناء جديدة';
$title = $isEdit ? 'تعديل خطة البناء' : 'إضافة خطة بناء';
$action = $isEdit
? route('student-construction-details.update', $detail)
: route('student-construction-details.store');
$method = $isEdit ? 'PUT' : 'POST';
$btnText = $isEdit ? 'حفظ التعديلات' : 'إضافة الخطة';
$icon = $isEdit ? 'fa-edit' : 'fa-plus-circle';

$isGroupPlan = isset($circle) || ($isEdit && $detail->circle_id && !$detail->student_id);
$isIndividualPlan = isset($student) || ($isEdit && $detail->student_id);

$backUrl = match(true) {
isset($circle) => route('circles.show', $circle),
$isEdit && $detail->circle_id && !$detail->student_id => route('circles.show', $detail->circle_id),
$isEdit && $detail->student_id => route('students.show', $detail->student_id),
isset($student) => route('students.show', $student),
default => url()->previous(),
};
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
                @if(isset($circle))
                <p class="text-sm text-gray-500 mt-1">
                    الحلقة: <span class="font-medium text-gray-700">{{ $circle->name }}</span>
                    <span class="inline-block mx-2 text-gray-300">|</span>
                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">خطة جماعية</span>
                </p>
                @elseif(isset($student))
                <p class="text-sm text-gray-500 mt-1">
                    الطالب: <span class="font-medium text-gray-700">{{ $student->name }}</span>
                    <span class="inline-block mx-2 text-gray-300">|</span>
                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">خطة فردية</span>
                </p>
                @elseif($isEdit && $detail->circle && !$detail->student_id)
                <p class="text-sm text-gray-500 mt-1">
                    الحلقة: <span class="font-medium text-gray-700">{{ $detail->circle->name }}</span>
                    <span class="inline-block mx-2 text-gray-300">|</span>
                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">خطة جماعية</span>
                </p>
                @elseif($isEdit && $detail->student)
                <p class="text-sm text-gray-500 mt-1">
                    الطالب: <span class="font-medium text-gray-700">{{ $detail->student->name }}</span>
                    <span class="inline-block mx-2 text-gray-300">|</span>
                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">خطة فردية</span>
                </p>
                @endif
            </div>

            <form action="{{ $action }}" method="POST" class="p-6">
                @csrf
                @if($isEdit) @method($method) @endif

                @if(isset($circle))
                <input type="hidden" name="circle_id" value="{{ $circle->id }}">
                @elseif($isEdit && $detail->circle_id && !$detail->student_id)
                <input type="hidden" name="circle_id" value="{{ $detail->circle_id }}">
                @endif

                @if(isset($student))
                <input type="hidden" name="student_id" value="{{ $student->id }}">
                @elseif($isEdit && $detail->student_id)
                <input type="hidden" name="student_id" value="{{ $detail->student_id }}">
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    {{-- نظام الدراسة --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">نظام الدراسة</label>
                        @if($isGroupPlan)
                        <input type="hidden" name="study_system" value="group">
                        <div class="w-full border border-gray-200 bg-blue-50 rounded-lg px-4 py-2.5 text-sm text-blue-800 font-medium flex items-center gap-2">
                            <i class="fas fa-users text-blue-500"></i>
                            جماعي
                            <span class="text-xs text-blue-400 mr-auto">جميع طلاب الحلقة يتبعون هذه الخطة</span>
                        </div>
                        @elseif($isIndividualPlan)
                        <input type="hidden" name="study_system" value="individual">
                        <div class="w-full border border-gray-200 bg-green-50 rounded-lg px-4 py-2.5 text-sm text-green-800 font-medium flex items-center gap-2">
                            <i class="fas fa-user text-green-500"></i>
                            فردي
                            <span class="text-xs text-green-400 mr-auto">خطة خاصة بهذا الطالب</span>
                        </div>
                        @else
                        <select name="study_system" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#0a5c36] outline-none @error('study_system') border-red-300 @enderror" required>
                            <option value="">اختر النظام</option>
                            <option value="group" {{ old('study_system', $detail->study_system ?? '') == 'group' ? 'selected' : '' }}>جماعي</option>
                            <option value="individual" {{ old('study_system', $detail->study_system ?? '') == 'individual' ? 'selected' : '' }}>فردي</option>
                        </select>
                        @endif
                        @error('study_system')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- السورة الحالية --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">السورة الحالية</label>
                        <x-searchable-select
                            name="current_surah_id"
                            :options="collect($surahs ?? [])->map(fn($surah) => ['value' => $surah->id, 'label' => $surah->number . '. ' . $surah->name_arabic])->values()"
                            placeholder="اختر السورة"
                            searchPlaceholder="ابحث عن سورة..."
                            :defaultValue="old('current_surah_id', $detail->current_surah_id ?? '')" />
                        @error('current_surah_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- خطة الحفظ الجديد --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            خطة الحفظ الجديد <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="new_memorization_plan"
                            value="{{ old('new_memorization_plan', $detail->new_memorization_plan ?? '') }}"
                            placeholder="مثال: 5 سطور يومياً"
                            dir="rtl"
                            class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#0a5c36] outline-none @error('new_memorization_plan') border-red-300 @enderror" required>
                        @error('new_memorization_plan')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- خطة المراجعة --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            خطة المراجعة <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="revision_plan"
                            value="{{ old('revision_plan', $detail->revision_plan ?? '') }}"
                            placeholder="مثال: وجه يومياً"
                            dir="rtl"
                            class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#0a5c36] outline-none @error('revision_plan') border-red-300 @enderror" required>
                        @error('revision_plan')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- خطة الحفظ القديم --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            خطة الحفظ القديم <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="old_memorization_plan"
                            value="{{ old('old_memorization_plan', $detail->old_memorization_plan ?? '') }}"
                            placeholder="مثال: حزب أسبوعياً"
                            dir="rtl"
                            class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#0a5c36] outline-none @error('old_memorization_plan') border-red-300 @enderror" required>
                        @error('old_memorization_plan')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- تقييم التسكين --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">تقييم التسكين</label>
                        <textarea name="placement_evaluation" rows="3" placeholder="نتائج تقييم التسكين..." dir="rtl"
                            class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#0a5c36] outline-none @error('placement_evaluation') border-red-300 @enderror">{{ old('placement_evaluation', $detail->placement_evaluation ?? '') }}</textarea>
                        @error('placement_evaluation')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="flex justify-between items-center mt-8 pt-5 border-t border-gray-100">
                    <a href="{{ $backUrl }}" class="text-gray-500 hover:text-gray-700 text-sm flex items-center gap-2 transition-colors">
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