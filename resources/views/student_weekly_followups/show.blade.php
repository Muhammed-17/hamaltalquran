@php
$discipline = $studentWeeklyFollowup->discipline;
$tajweed = $studentWeeklyFollowup->tajweedAssessment;
$foundation = $studentWeeklyFollowup->foundationLevel;
$eduLesson = $studentWeeklyFollowup->educationalLessonAssessment;

$sections = [
'new_memorization' => ['title' => 'الحفظ الجديد', 'header' => 'bg-emerald-600', 'relation' => 'newMemorizations'],
'revision' => ['title' => 'المراجعة', 'header' => 'bg-sky-600', 'relation' => 'revisions'],
'old_memorization' => ['title' => 'الحفظ القديم', 'header' => 'bg-amber-600', 'relation' => 'oldMemorizations'],
];

$activityTypeLabels = [
'معرفي' => 'معرفي/علمي',
'اجتماعي' => 'اجتماعي',
'ترفيهي' => 'ترفيهي',
'إيماني' => 'إيماني/دعوي',
];
$activities = $studentWeeklyFollowup->activities ?? collect();
@endphp
<x-layouts.markaz-layout>
    <div class="max-w-5xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">
                    متابعة {{ $studentWeeklyFollowup->student->name ?? '' }}
                </h2>
                <p class="text-gray-500 mt-1 text-sm">
                    {{ $studentWeeklyFollowup->week_start?->format('Y-m-d') }} — {{ $studentWeeklyFollowup->week_end?->format('Y-m-d') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                @can('edit student weekly followups')
                <a href="{{ route('student-weekly-followups.edit', $studentWeeklyFollowup) }}"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#0a5c36] text-white font-semibold hover:bg-[#0d7a48] transition-colors">
                    تعديل
                </a>
                @endcan
                <a href="{{ route('student-weekly-followups.index-individual') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors">
                    رجوع
                </a>
            </div>
        </div>

        {{-- تحليل الأداء --}}
        <div class="bg-white rounded-[2rem] p-6 gap-6 mb-6 shadow-sm border border-gray-100">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <h3 class="font-black text-gray-900">تحليل الأداء</h3>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between text-sm mb-1">
                    <span class="text-gray-500 font-medium">نسبة الالتزام</span>
                    <span class="text-emerald-600 font-black">{{ $attendanceRate }}%</span>
                </div>
                <div class="relative w-full h-3 bg-gray-100 rounded-full overflow-hidden">
                    <div class="absolute top-0 left-0 h-full bg-emerald-500 rounded-full transition-all duration-1000"
                        style="width:{{ $attendanceRate }}%"></div>
                </div>
                <div class="grid grid-cols-2 gap-3 mt-4">
                    <div class="bg-gray-50 rounded-2xl p-4 text-center">
                        <span class="block text-2xl font-black text-emerald-600">{{ $presentCount }}</span>
                        <span class="text-xs text-gray-500 font-bold">يوم حضور</span>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 text-center">
                        <span class="block text-2xl font-black text-rose-500">{{ $absentCount }}</span>
                        <span class="text-xs text-gray-500 font-bold">يوم غياب</span>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 text-center">
                        <span class="block text-2xl font-black text-amber-500">{{ $lateCount }}</span>
                        <span class="text-xs text-gray-500 font-bold">يوم تأخير</span>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 text-center">
                        <span class="block text-2xl font-black text-sky-500">{{ $excusedCount }}</span>
                        <span class="text-xs text-gray-500 font-bold">يوم بعذر</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

            @foreach($sections as $key => $section)
            @php
            $sectionData = $studentWeeklyFollowup->{$section['relation']};
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="{{ $section['header'] }} text-white px-5 py-3">
                    <h3 class="text-base font-bold">{{ $section['title'] }}</h3>
                </div>
                <div class="p-5 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">من</label>
                        <p class="text-sm text-gray-900">
                            {{ $sectionData?->fromSurah?->name_arabic ?? '—' }}
                            @if($sectionData?->plan_from_ayah)
                            : {{ $sectionData->plan_from_ayah }}
                            @endif
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">إلى</label>
                        <p class="text-sm text-gray-900">
                            {{ $sectionData?->toSurah?->name_arabic ?? '—' }}
                            @if($sectionData?->plan_to_ayah)
                            : {{ $sectionData->plan_to_ayah }}
                            @endif
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">مقارنة الخطة</label>
                        <p class="text-sm text-gray-900">{{ $sectionData?->plan_comparison ?? '—' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">فرق التقدم</label>
                        <p class="text-sm text-gray-900">{{ $sectionData?->progress_difference ?? '—' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">المستوى</label>
                        <p class="text-sm text-gray-900">{{ $sectionData?->average_level ?? '—' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">ملاحظات</label>
                        <p class="text-sm text-gray-600">{{ $sectionData?->notes ?? '—' }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-indigo-600 text-white px-5 py-3">
                    <h3 class="text-base font-bold">الانضباط</h3>
                </div>
                <div class="p-5 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">المستوى</label>
                        <p class="text-sm text-gray-900">{{ $discipline?->level ?? '—' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">الإنجاز</label>
                        <p class="text-sm text-gray-600">{{ $discipline?->notes ?? '—' }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-rose-600 text-white px-5 py-3">
                    <h3 class="text-base font-bold">التجويد</h3>
                </div>
                <div class="p-5 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">المستوى</label>
                        <p class="text-sm text-gray-900">{{ $tajweed?->level ?? '—' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">الإنجاز</label>
                        <p class="text-sm text-gray-600">{{ $tajweed?->notes ?? '—' }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-violet-600 text-white px-5 py-3">
                    <h3 class="text-base font-bold">مستوى التأسيس</h3>
                </div>
                <div class="p-5 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">المستوى</label>
                        <p class="text-sm text-gray-900">{{ $foundation?->level ?? '—' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">الإنجاز</label>
                        <p class="text-sm text-gray-600">{{ $foundation?->notes ?? '—' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-blue-600 text-white px-5 py-3">
                    <h3 class="text-base font-bold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        الدرس التربوي
                    </h3>
                </div>
                <div class="p-5 space-y-3">
                    @if($eduLesson?->lesson)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">عنوان الدرس</label>
                        <p class="text-sm font-bold text-gray-900">{{ $eduLesson->lesson->title }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الوصف</label>
                        <p class="text-sm text-gray-600">{{ $eduLesson->lesson->description }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">المستوى</label>
                        <p class="text-sm text-gray-900">{{ $eduLesson->level ?? '—' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
                        <p class="text-sm text-gray-600">{{ $eduLesson->notes ?? '—' }}</p>
                    </div>
                    @else
                    <div class="bg-amber-50 rounded-lg p-3 border border-amber-200">
                        <p class="text-xs text-amber-700 text-center">لا يوجد درس تربوي مسجل.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-orange-600 text-white px-6 py-4">
                    <h3 class="text-lg font-bold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        الأنشطة الطلابية
                    </h3>
                </div>
                <div class="p-6">
                    @if($activities->isEmpty())
                    <div class="text-center py-8 text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p>لا توجد أنشطة مسجلة</p>
                    </div>
                    @else
                    <div class="space-y-3">
                        @foreach($activities as $activity)
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">نوع النشاط</label>
                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ $activityTypeLabels[$activity->activity_type] ?? $activity->activity_type ?? '—' }}
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">اسم النشاط</label>
                                    <p class="text-sm font-semibold text-gray-900">{{ $activity->activity_name ?? '—' }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">تاريخ النشاط</label>
                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ $activity->activity_date?->format('Y-m-d') ?? '—' }}
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">ملاحظات</label>
                                    <p class="text-sm text-gray-600">{{ $activity->notes ?? '—' }}</p>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden gap-6 mb-6">
            <div class="bg-gray-800 text-white px-6 py-4">
                <h3 class="text-lg font-bold">ملاحظات عامة</h3>
            </div>
            <div class="p-6">
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $studentWeeklyFollowup->notes ?? '—' }}</p>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-6 mb-6 ">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-teal-600 text-white px-5 py-3">
                    <h3 class="text-base font-bold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                        التوصية الأسبوعية
                    </h3>
                </div>
                <div class="p-5 space-y-3">
                    @php
                    $recommendation = $studentWeeklyFollowup->recommendation;
                    @endphp
                    @if($recommendation)
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">التوصية</label>
                        <p class="text-sm text-gray-800 leading-relaxed">
                            {{ $recommendation->supervisor_recommendation ?? $recommendation->generated_recommendation }}
                        </p>
                    </div>
                    @if($recommendation->supervisor_recommendation && $recommendation->signed_at)
                    <div class="pt-2 border-t border-gray-100">
                        <span class="text-xs text-gray-400">
                            تمت المراجعة والتوقيع بتاريخ {{ $recommendation->signed_at->format('Y-m-d') }}
                        </span>
                    </div>
                    @endif
                    @else
                    <div class="bg-amber-50 rounded-lg p-3 border border-amber-200">
                        <p class="text-xs text-amber-700 text-center">لم يتم توليد توصية لهذه المتابعة بعد.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.markaz-layout>