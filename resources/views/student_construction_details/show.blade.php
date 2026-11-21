{{-- resources/views/student_construction_details/show.blade.php --}}
@php
$detail = $studentConstructionDetail;
$isGroupPlan = $detail->circle_id && !$detail->student_id;
$backUrl = $detail->student_id
    ? route('students.show', $detail->student_id)
    : ($detail->circle_id ? route('circles.show', $detail->circle_id) : route('students.index'));
$systemLabel = match($detail->study_system) {
    'group' => 'جماعي',
    'individual' => 'فردي',
    default => $detail->study_system,
};
@endphp

<x-layouts.markaz-layout>
    <x-slot name="title">تفاصيل خطة البناء</x-slot>

    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-xl shadow-sm">
            <div class="px-6 py-5 border-b border-gray-100">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                        <i class="fas fa-clipboard-list text-blue-500"></i>
                    </div>
                    تفاصيل خطة البناء
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    @if($isGroupPlan)
                    الحلقة: <span class="font-medium text-gray-700">{{ $detail->circle->name ?? '-' }}</span>
                    <span class="inline-block mx-2 text-gray-300">|</span>
                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">خطة جماعية</span>
                    @else
                    الطالب: <span class="font-medium text-gray-700">{{ $detail->student->name ?? '-' }}</span>
                    <span class="inline-block mx-2 text-gray-300">|</span>
                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">خطة فردية</span>
                    @endif
                </p>
            </div>

            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-3 border-b border-gray-50">
                        <span class="text-gray-500 text-sm">نظام الدراسة</span>
                        <span class="font-semibold text-gray-800">{{ $systemLabel ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-gray-50">
                        <span class="text-gray-500 text-sm">السورة الحالية</span>
                        <span class="font-semibold text-gray-800">{{ $detail->currentSurah->name_arabic ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-gray-50">
                        <span class="text-gray-500 text-sm">خطة الحفظ الجديد</span>
                        <span class="font-semibold text-gray-800">{{ $detail->new_memorization_plan ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-gray-50">
                        <span class="text-gray-500 text-sm">خطة المراجعة</span>
                        <span class="font-semibold text-gray-800">{{ $detail->revision_plan ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-gray-50">
                        <span class="text-gray-500 text-sm">خطة الحفظ القديم</span>
                        <span class="font-semibold text-gray-800">{{ $detail->old_memorization_plan ?? '-' }}</span>
                    </div>
                    @if($detail->placement_evaluation)
                    <div class="py-3">
                        <span class="text-gray-500 text-sm block mb-2">تقييم التسكين</span>
                        <p class="text-gray-700 text-sm bg-gray-50 p-3 rounded-lg">{{ $detail->placement_evaluation }}</p>
                    </div>
                    @endif
                </div>

                <div class="flex justify-between items-center mt-8 pt-5 border-t border-gray-100">
                    <a href="{{ $backUrl }}" class="text-gray-500 hover:text-gray-700 text-sm flex items-center gap-2 transition-colors">
                        <i class="fas fa-arrow-right"></i> رجوع
                    </a>
                    @can('update students')
                    <a href="{{ route('student-construction-details.edit', $detail) }}" class="bg-amber-500 hover:bg-amber-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                        <i class="fas fa-edit"></i> تعديل الخطة
                    </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-layouts.markaz-layout>
