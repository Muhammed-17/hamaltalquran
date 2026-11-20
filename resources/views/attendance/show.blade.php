<x-layouts.markaz-layout>
    <x-slot name="title">تفاصيل سجل الحضور</x-slot>

    <div class="max-w-4xl mx-auto py-8 px-4">
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">تفاصيل سجل الحضور</h1>
                <p class="text-gray-500 mt-1">{{ $attendance->student->name }} - {{ $attendance->date->translatedFormat('d F Y') }}</p>
            </div>
            <div class="flex gap-2">
                @can('update', $attendance)
                <a href="{{ route('attendance.edit', $attendance) }}"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    تعديل
                </a>
                @endcan
                <a href="{{ route('attendance.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    رجوع
                </a>
            </div>
        </div>

        <!-- Status Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full flex items-center justify-center
                    @if($attendance->status === 'present') bg-green-100 text-green-600
                    @elseif($attendance->status === 'absent') bg-red-100 text-red-600
                    @elseif($attendance->status === 'late') bg-yellow-100 text-yellow-600
                    @else bg-blue-100 text-blue-600 @endif">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        @if($attendance->status === 'present')
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        @elseif($attendance->status === 'absent')
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        @elseif($attendance->status === 'late')
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        @else
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        @endif
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900">{{ $attendance->status_label }}</h2>
                    <p class="text-gray-500">{{ $attendance->date->translatedFormat('l، d F Y') }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Student Info -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    معلومات الطالب
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-500">الاسم</span>
                        <span class="font-medium">{{ $attendance->student->name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">الحلقة</span>
                        <span class="font-medium">{{ $attendance->student->circle->name ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">المعلم</span>
                        <span class="font-medium">{{ $attendance->student->circle->mainTeachers->first()?->name ?? '-' }}</span>
                    </div>
                </div>
            </div>

            <!-- Record Info -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    معلومات السجل
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-500">المسجل</span>
                        <span class="font-medium">{{ $attendance->user->name ?? 'نظام' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">تاريخ التسجيل</span>
                        <span class="font-medium">{{ $attendance->created_at->translatedFormat('d F Y - H:i') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">آخر تحديث</span>
                        <span class="font-medium">{{ $attendance->updated_at->translatedFormat('d F Y - H:i') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes -->
        @if($attendance->notes)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">ملاحظات</h3>
            <p class="text-gray-700 bg-gray-50 p-4 rounded-lg">{{ $attendance->notes }}</p>
        </div>
        @endif

        <!-- Monthly Summary -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">ملخص الشهر الحالي</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">{{ $monthlySummary['present'] ?? 0 }}</div>
                    <div class="text-sm text-green-700">حاضر</div>
                </div>
                <div class="text-center p-4 bg-red-50 rounded-lg">
                    <div class="text-2xl font-bold text-red-600">{{ $monthlySummary['absent'] ?? 0 }}</div>
                    <div class="text-sm text-red-700">غائب</div>
                </div>
                <div class="text-center p-4 bg-yellow-50 rounded-lg">
                    <div class="text-2xl font-bold text-yellow-600">{{ $monthlySummary['late'] ?? 0 }}</div>
                    <div class="text-sm text-yellow-700">متأخر</div>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">{{ $monthlySummary['excused'] ?? 0 }}</div>
                    <div class="text-sm text-blue-700">بعذر</div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="flex justify-between mt-6">
            @if($previousRecord)
            <a href="{{ route('attendance.show', $previousRecord) }}"
                class="inline-flex items-center px-4 py-2 text-indigo-600 hover:text-indigo-800">
                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                السجل السابق
            </a>
            @else
            <span></span>
            @endif

            @if($nextRecord)
            <a href="{{ route('attendance.show', $nextRecord) }}"
                class="inline-flex items-center px-4 py-2 text-indigo-600 hover:text-indigo-800">
                السجل التالي
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </a>
            @else
            <span></span>
            @endif
        </div>
    </div>
</x-layouts.markaz-layout>