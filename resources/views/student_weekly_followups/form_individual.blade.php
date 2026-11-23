@php
$isShow = ($mode === 'show');
$isEdit = ($mode === 'edit');
$isCreate = ($mode === 'create');

$formAction = $isCreate
? route('student-weekly-followups.store-individual')
: ($isEdit ? route('student-weekly-followups.update-individual', $studentWeeklyFollowup->id) : '');

$studyDays = [];
if ($isShow && $studentWeeklyFollowup) {
$studyDays = $studentWeeklyFollowup->study_days ?? [];
} elseif (!$isShow) {
$studyDays = old('study_days', $studentWeeklyFollowup->study_days ?? $weekDates['study_days'] ?? []);
}

$days = [
'Saturday' => 'السبت', 'Sunday' => 'الأحد', 'Monday' => 'الإثنين',
'Tuesday' => 'الثلاثاء', 'Wednesday' => 'الأربعاء',
'Thursday' => 'الخميس', 'Friday' => 'الجمعة'
];

// For edit/show mode: pre-populate student select with current student
$studentOptions = [];
if (($isEdit || $isShow) && $studentWeeklyFollowup?->student) {
$studentOptions = [$studentWeeklyFollowup->student_id => $studentWeeklyFollowup->student->name];
}

// Pre-load assessment data
$discipline = $studentWeeklyFollowup?->discipline;
$tajweed = $studentWeeklyFollowup?->tajweedAssessment;
$foundation = $studentWeeklyFollowup?->foundationLevel;
$eduLesson = $studentWeeklyFollowup?->educationalLessonAssessment;

// Assessment levels
$levels = \App\Enums\AssessmentLevel::options();

// Activity types
$activityTypes = [
'معرفي' => 'معرفي/علمي',
'اجتماعي' => 'اجتماعي',
'ترفيهي' => 'ترفيهي',
'إيماني' => 'إيماني/دعوي',
];

// Activities data
$activities = $studentWeeklyFollowup?->activities ?? collect();
@endphp
@if($isCreate || $isEdit)
<form action="{{ $formAction }}" method="POST" id="followup-form" class="space-y-6"
    x-data="individualPlanForm({
        circles: {{ json_encode($circles->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values()) }},
        allStudents: {{ json_encode($circles->flatMap(fn($c) => $c->students->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'circle_id' => $c->id]))->values()) }},
        selectedCircleId: {{ old('circle_id', $studentWeeklyFollowup->circle_id ?? $defaultCircleId ?? 'null') }},
        selectedStudentId: {{ old('student_id', $studentWeeklyFollowup->student_id ?? 'null') }},
        excludedStudentIds: {{ json_encode($excludedStudentIds ?? []) }},
        hideCircleField: {{ $hideCircleField ? 'true' : 'false' }},
        defaultCircleId: {{ $defaultCircleId ?? 'null' }},
        isShow: {{ $isShow ? 'true' : 'false' }}
    })"
    x-init="init()">
    @csrf
    @if($isEdit)
    @method('PUT')
    @endif
    @else
    <div class="space-y-6">
        @endif

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- Student & Plan Data Section -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-[#0a5c36] text-white px-6 py-4">
                <h3 class="text-lg font-bold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    بيانات الطالب والخطة
                </h3>
            </div>
            <div class="p-6 space-y-4">

                @if($isShow)
                <!-- ═══════ SHOW MODE: Static Display ═══════ -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الطالب</label>
                        <p class="text-base font-semibold text-gray-900">{{ $studentWeeklyFollowup->student->name ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الحلقة</label>
                        <p class="text-base font-semibold text-gray-900">{{ $studentWeeklyFollowup->circle->name ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">المعلم</label>
                        <p class="text-base font-semibold text-gray-900">{{ $studentWeeklyFollowup->teacher->user->name ?? '-' }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-gray-100">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">بداية الأسبوع</label>
                        <p class="text-sm font-semibold text-gray-900">{{ $studentWeeklyFollowup->week_start?->format('Y-m-d') ?? $studentWeeklyFollowup->week_start ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نهاية الأسبوع</label>
                        <p class="text-sm font-semibold text-gray-900">{{ $studentWeeklyFollowup->week_end?->format('Y-m-d') ?? $studentWeeklyFollowup->week_end ?? '-' }}</p>
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-100">
                    <label class="block text-sm font-medium text-gray-700 mb-2">أيام الدراسة</label>
                    <div class="flex flex-wrap gap-2">
                        @php $savedDays = $studentWeeklyFollowup->study_days ?? []; @endphp
                        @foreach($days as $en => $ar)
                        @if(in_array($en, $savedDays))
                        <span class="inline-flex items-center px-3 py-1 rounded-lg bg-[#0a5c36]/10 text-[#0a5c36] text-sm font-medium border border-[#0a5c36]/20">
                            {{ $ar }}
                        </span>
                        @endif
                        @endforeach
                        @if(empty($savedDays))
                        <span class="text-sm text-gray-400">لم يتم تحديد أيام</span>
                        @endif
                    </div>
                </div>

                @else
                <!-- ═══════ CREATE/EDIT MODE: Form Fields ═══════ -->

                <!-- Circle ID (conditional) -->
                @if(!$hideCircleField)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">الحلقة <span class="text-red-500">*</span></label>
                    <x-searchable-select
                        name="circle_id"
                        :options="$circles->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values()"
                        :defaultValue="old('circle_id', $studentWeeklyFollowup->circle_id ?? $defaultCircleId ?? '')"
                        placeholder="اختر الحلقة"
                        searchPlaceholder="ابحث عن حلقة..." />
                    @error('circle_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                @else
                <input type="hidden" name="circle_id" value="{{ $defaultCircleId }}">
                @endif

                <!-- Student ID -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">الطالب <span class="text-red-500">*</span></label>
                    <x-searchable-select
                        name="student_id"
                        :options="collect($studentOptions)->map(fn($label, $value) => ['value' => $value, 'label' => $label])->values()"
                        :defaultValue="old('student_id', $studentWeeklyFollowup->student_id ?? '')"
                        placeholder="اختر الطالب"
                        searchPlaceholder="ابحث عن طالب..." />
                    @error('student_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <!-- Teacher ID -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">المعلم <span class="text-red-500">*</span></label>
                    <x-searchable-select
                        name="teacher_id"
                        :options="$teachers->map(fn($t) => ['value' => $t->id, 'label' => $t->user->name ?? $t->name])->values()"
                        :defaultValue="old('teacher_id', $studentWeeklyFollowup->teacher_id ?? '')"
                        placeholder="اختر المعلم"
                        searchPlaceholder="ابحث عن معلم..." />
                    @error('teacher_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <!-- Week Dates -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">بداية الأسبوع <span class="text-red-500">*</span></label>
                        <input type="date" name="week_start"
                            class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36]"
                            value="{{ old('week_start', isset($studentWeeklyFollowup) ? $studentWeeklyFollowup->week_start?->format('Y-m-d') : ($weekDates['week_start'] ?? '')) }}">
                        @error('week_start')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">نهاية الأسبوع <span class="text-red-500">*</span></label>
                        <input type="date" name="week_end"
                            class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36]"
                            value="{{ old('week_end', isset($studentWeeklyFollowup) ? $studentWeeklyFollowup->week_end?->format('Y-m-d') : ($weekDates['week_end'] ?? '')) }}">
                        @error('week_end')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <!-- Study Days -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">أيام الدراسة</label>
                    <div class="flex flex-wrap gap-3">
                        @foreach($days as $en => $ar)
                        <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50 transition-colors has-[:checked]:bg-[#0a5c36]/5 has-[:checked]:border-[#0a5c36]">
                            <input type="checkbox" name="study_days[]" value="{{ $en }}"
                                class="rounded border-gray-300 text-[#0a5c36] focus:ring-[#0a5c36]"
                                {{ in_array($en, $studyDays) ? 'checked' : '' }}>
                            <span class="text-sm text-gray-700">{{ $ar }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- Plan Items: New Memorization, Revision, Old Memorization -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        @php
        $sections = [
        'new_memorization' => [
        'title' => 'الحفظ الجديد',
        'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
        'header' => 'bg-emerald-600',
        'relation' => 'newMemorizations',
        'prefix' => 'memorization',
        'level_name' => 'new_memorization_level',
        ],
        'revision' => [
        'title' => 'المراجعة',
        'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
        'header' => 'bg-sky-600',
        'relation' => 'revisions',
        'prefix' => 'revision',
        'level_name' => 'revision_level',
        ],
        'old_memorization' => [
        'title' => 'الحفظ القديم',
        'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
        'header' => 'bg-amber-600',
        'relation' => 'oldMemorizations',
        'prefix' => 'old_revision',
        'level_name' => 'old_memorization_level',
        ],
        ];
        @endphp

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @foreach($sections as $key => $section)
            @php
            $sectionData = null;
            if (($isShow || $isEdit) && isset($studentWeeklyFollowup)) {
            $relation = $section['relation'];
            $sectionData = $studentWeeklyFollowup->{$relation}->first();
            }

            $prefix = $section['prefix'];
            $oldFromSurah = old("{$prefix}_from_surah_id", $sectionData?->plan_from_surah_id ?? '');
            $oldFromAyah = old("{$prefix}_from_ayah", $sectionData?->plan_from_ayah ?? '');
            $oldToSurah = old("{$prefix}_to_surah_id", $sectionData?->plan_to_surah_id ?? '');
            $oldToAyah = old("{$prefix}_to_ayah", $sectionData?->plan_to_ayah ?? '');
            $oldLevel = old($section['level_name'], $sectionData?->average_level?->value ?? '');
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="{{ $section['header'] }} text-white px-5 py-3">
                    <h3 class="text-base font-bold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $section['icon'] }}" />
                        </svg>
                        {{ $section['title'] }}
                    </h3>
                </div>
                <div class="p-5 space-y-3">
                    @if($isShow)
                    <!-- Show Mode: Text Display -->
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="bg-gray-50 rounded-lg p-3">
                            <span class="block text-xs text-gray-500 mb-1">من سورة</span>
                            <span class="font-semibold text-gray-900">{{ $sectionData?->fromSurah?->name_arabic ?? '-' }}</span>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <span class="block text-xs text-gray-500 mb-1">من آية</span>
                            <span class="font-semibold text-gray-900">{{ $sectionData?->plan_from_ayah ?? '-' }}</span>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <span class="block text-xs text-gray-500 mb-1">إلى سورة</span>
                            <span class="font-semibold text-gray-900">{{ $sectionData?->toSurah?->name_arabic ?? '-' }}</span>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <span class="block text-xs text-gray-500 mb-1">إلى آية</span>
                            <span class="font-semibold text-gray-900">{{ $sectionData?->plan_to_ayah ?? '-' }}</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="bg-gray-50 rounded-lg p-3">
                            <span class="block text-xs text-gray-500 mb-1">مقارنة الخطة</span>
                            <span class="font-semibold text-gray-900">{{ $sectionData?->plan_comparison ?? '-' }}</span>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <span class="block text-xs text-gray-500 mb-1">فرق التقدم</span>
                            <span class="font-semibold text-gray-900">{{ $sectionData?->progress_difference ?? '-' }}</span>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <span class="block text-xs text-gray-500 mb-1">المستوى</span>
                        <span class="font-semibold text-gray-900">{{ $sectionData?->average_level ?? '-' }}</span>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <span class="block text-xs text-gray-500 mb-1">ملاحظات</span>
                        <span class="font-semibold text-gray-900">{{ $sectionData?->notes ?? '-' }}</span>
                    </div>
                    @else
                    <!-- Create/Edit Mode: Form Fields -->
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">من سورة</label>
                        <x-searchable-select
                            name="{{ $prefix }}_from_surah_id"
                            :options="$surahs->map(fn($s) => ['value' => $s->id, 'label' => $s->name_arabic])->values()"
                            :defaultValue="$oldFromSurah"
                            placeholder="اختر السورة"
                            searchPlaceholder="ابحث عن سورة..." />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">من آية</label>
                        <input type="number" name="{{ $prefix }}_from_ayah"
                            class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm"
                            value="{{ $oldFromAyah }}" min="1">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">إلى سورة</label>
                        <x-searchable-select
                            name="{{ $prefix }}_to_surah_id"
                            :options="$surahs->map(fn($s) => ['value' => $s->id, 'label' => $s->name_arabic])->values()"
                            :defaultValue="$oldToSurah"
                            placeholder="اختر السورة"
                            searchPlaceholder="ابحث عن سورة..." />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">إلى آية</label>
                        <input type="number" name="{{ $prefix }}_to_ayah"
                            class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm"
                            value="{{ $oldToAyah }}" min="1">
                    </div>
                    <!-- Plan Comparison & Progress Difference -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">مقارنة الخطة</label>
                            <select name="{{ $prefix }}_plan_comparison"
                                class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm">
                                <option value="">اختر</option>
                                <option value="مطابق" {{ old($prefix.'_plan_comparison', $sectionData?->plan_comparison ?? '') == 'مطابق' ? 'selected' : '' }}>مطابق</option>
                                <option value="متقدم" {{ old($prefix.'_plan_comparison', $sectionData?->plan_comparison ?? '') == 'متقدم' ? 'selected' : '' }}>متقدم</option>
                                <option value="متأخر" {{ old($prefix.'_plan_comparison', $sectionData?->plan_comparison ?? '') == 'متأخر' ? 'selected' : '' }}>متأخر</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">فرق التقدم</label>
                            <input type="text" name="{{ $prefix }}_progress_difference"
                                class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm"
                                value="{{ old($prefix.'_progress_difference', $sectionData?->progress_difference ?? '') }}"
                                placeholder="مثال: +3 آيات">
                        </div>
                    </div>
                    <!-- Level -->
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">المستوى</label>
                        <select name="{{ $section['level_name'] }}"
                            class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm">
                            <option value="">اختر المستوى</option>
                            @foreach($levels as $val => $label)
                            <option value="{{ $val }}" {{ $oldLevel == $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <!-- Notes -->
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">ملاحظات</label>
                        <textarea name="{{ $prefix }}_notes" rows="2"
                            class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm resize-none p-2"
                            placeholder="أي ملاحظات إضافية...">{{ old($prefix.'_notes', $sectionData?->notes ?? '') }}</textarea>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- Tajweed & Foundation Level & Discipline Section -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Discipline -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-indigo-600 text-white px-5 py-3">
                    <h3 class="text-base font-bold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        الانضباط
                    </h3>
                </div>
                <div class="p-5 space-y-3">
                    @if($isShow)
                    <div class="bg-gray-50 rounded-lg p-3">
                        <span class="block text-xs text-gray-500 mb-1">المستوى</span>
                        <span class="font-semibold text-gray-900">{{ $discipline?->level ?? '-' }}</span>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <span class="block text-xs text-gray-500 mb-1">الإنجاز</span>
                        <span class="font-semibold text-gray-900">{{ $discipline?->notes ?? '-' }}</span>
                    </div>
                    @else
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">المستوى</label>
                        <select name="discipline_level"
                            class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm">
                            <option value="">اختر المستوى</option>
                            @foreach($levels as $val => $label)
                            <option value="{{ $val }}" {{ old('discipline_level', $discipline?->level?->value ?? '') == $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">الإنجاز / ملاحظات</label>
                        <input type="text" name="discipline_achievement"
                            class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm"
                            value="{{ old('discipline_achievement', $discipline?->notes ?? '') }}"
                            placeholder="أدخل الإنجاز">
                    </div>
                    @endif
                </div>
            </div>

            <!-- Tajweed -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-rose-600 text-white px-5 py-3">
                    <h3 class="text-base font-bold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        التجويد
                    </h3>
                </div>
                <div class="p-5 space-y-3">
                    @if($isShow)
                    <div class="bg-gray-50 rounded-lg p-3">
                        <span class="block text-xs text-gray-500 mb-1">المستوى</span>
                        <span class="font-semibold text-gray-900">{{ $tajweed?->level ?? '-' }}</span>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <span class="block text-xs text-gray-500 mb-1">الإنجاز</span>
                        <span class="font-semibold text-gray-900">{{ $tajweed?->notes ?? '-' }}</span>
                    </div>
                    @else
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">المستوى</label>
                        <select name="tajweed_level"
                            class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm">
                            <option value="">اختر المستوى</option>
                            @foreach($levels as $val => $label)
                            <option value="{{ $val }}" {{ old('tajweed_level', $tajweed?->level?->value ?? '') == $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">الإنجاز / ملاحظات</label>
                        <input type="text" name="tajweed_achievement"
                            class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm"
                            value="{{ old('tajweed_achievement', $tajweed?->notes ?? '') }}"
                            placeholder="أدخل الإنجاز">
                    </div>
                    @endif
                </div>
            </div>

            <!-- Foundation Level -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-violet-600 text-white px-5 py-3">
                    <h3 class="text-base font-bold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                        المستوى التأسيسي
                    </h3>
                </div>
                <div class="p-5 space-y-3">
                    @if($isShow)
                    <div class="bg-gray-50 rounded-lg p-3">
                        <span class="block text-xs text-gray-500 mb-1">المستوى</span>
                        <span class="font-semibold text-gray-900">{{ $foundation?->level ?? '-' }}</span>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <span class="block text-xs text-gray-500 mb-1">الإنجاز</span>
                        <span class="font-semibold text-gray-900">{{ $foundation?->notes ?? '-' }}</span>
                    </div>
                    @else
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">المستوى</label>
                        <select name="foundation_level_level"
                            class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm">
                            <option value="">اختر المستوى</option>
                            @foreach($levels as $val => $label)
                            <option value="{{ $val }}" {{ old('foundation_level_level', $foundation?->level?->value ?? '') == $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">الإنجاز / ملاحظات</label>
                        <input type="text" name="foundation_level_achievement"
                            class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm"
                            value="{{ old('foundation_level_achievement', $foundation?->notes ?? '') }}"
                            placeholder="أدخل الإنجاز">
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- Educational Lesson Section -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        @if(isset($educationalLesson) && $educationalLesson)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-teal-600 text-white px-6 py-4">
                <h3 class="text-lg font-bold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    الدرس التربوي: {{ $educationalLesson->title ?? 'الدرس التربوي' }}
                </h3>
            </div>
            <div class="p-6">
                <input type="hidden" name="educational_lesson_id" value="{{ $educationalLesson->id }}">
                @if($isShow)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 rounded-lg p-3">
                        <span class="block text-xs text-gray-500 mb-1">المستوى</span>
                        <span class="font-semibold text-gray-900">{{ $eduLesson?->level ?? '-' }}</span>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <span class="block text-xs text-gray-500 mb-1">ملاحظات</span>
                        <span class="font-semibold text-gray-900">{{ $eduLesson?->notes ?? '-' }}</span>
                    </div>
                </div>
                @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">المستوى</label>
                        <select name="educational_lesson_level"
                            class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36]">
                            <option value="">اختر المستوى</option>
                            @foreach($levels as $val => $label)
                            <option value="{{ $val }}" {{ old('educational_lesson_level', $eduLesson?->level?->value ?? '') == $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">ملاحظات</label>
                        <input type="text" name="educational_lesson_notes"
                            class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36]"
                            value="{{ old('educational_lesson_notes', $eduLesson?->notes ?? '') }}"
                            placeholder="ملاحظات الدرس التربوي">
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- Activities Section -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        @php
$activitiesForJs = old('activities')
    ? collect(old('activities'))
        ->filter(fn($a) => ($a['_deleted'] ?? 'false') !== 'true')
        ->map(fn($a) => [
            'activity_type' => $a['activity_type'] ?? '',
            'activity_name' => $a['activity_name'] ?? '',
            'activity_date' => $a['activity_date'] ?? '',
            'notes'         => $a['notes'] ?? '',
        ])
        ->values()
    : $activities->map(fn($a) => [
        'activity_type' => $a->activity_type,
        'activity_name' => $a->activity_name,
        'activity_date' => $a->activity_date?->format('Y-m-d'),
        'notes'         => $a->notes,
    ])->values();
@endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden"
    x-data="activitiesManager({
        initialActivities: {{ json_encode($activitiesForJs) }},
        isShow: {{ $isShow ? 'true' : 'false' }}
    })">
            <div class="bg-orange-600 text-white px-6 py-4 flex justify-between items-center">
                <h3 class="text-lg font-bold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    الأنشطة
                </h3>
                @if(!$isShow)
                <button type="button" @click="addActivity()"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white/20 hover:bg-white/30 text-white text-sm font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    إضافة نشاط
                </button>
                @endif
            </div>
            <div class="p-6">
                @if($isShow && $activities->isEmpty())
                <div class="text-center py-8 text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p>لا توجد أنشطة مسجلة</p>
                </div>
                @else
                <div class="space-y-3">
                    <template x-for="(activity, index) in activities" :key="index">
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            @if(!$isShow)
                            <input type="hidden" :name="`activities[${index}][_deleted]`" x-model="activity._deleted">
                            @endif
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">نوع النشاط</label>
                                    @if($isShow)
                                    <p class="text-sm font-semibold text-gray-900" x-text="activity.activity_type || '-'"></p>
                                    @else
                                    <select :name="`activities[${index}][activity_type]`"
                                        x-model="activity.activity_type"
                                        class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm">
                                        <option value="">اختر النوع</option>
                                        @foreach($activityTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @endif
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">اسم النشاط</label>
                                    @if($isShow)
                                    <p class="text-sm font-semibold text-gray-900" x-text="activity.activity_name || '-'"></p>
                                    @else
                                    <input type="text" :name="`activities[${index}][activity_name]`"
                                        x-model="activity.activity_name"
                                        class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm"
                                        placeholder="اسم النشاط">
                                    @endif
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">التاريخ</label>
                                    @if($isShow)
                                    <p class="text-sm font-semibold text-gray-900" x-text="activity.activity_date || '-'"></p>
                                    @else
                                    <input type="date" :name="`activities[${index}][activity_date]`"
                                        x-model="activity.activity_date"
                                        class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm">
                                    @endif
                                </div>
                                <div class="flex gap-2">
                                    <div class="flex-1">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">ملاحظات</label>
                                        @if($isShow)
                                        <p class="text-sm font-semibold text-gray-900" x-text="activity.notes || '-'"></p>
                                        @else
                                        <input type="text" :name="`activities[${index}][notes]`"
                                            x-model="activity.notes"
                                            class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm"
                                            placeholder="ملاحظات">
                                        @endif
                                    </div>
                                    @if(!$isShow)
                                    <button type="button" @click="removeActivity(index)"
                                        class="px-2 py-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors self-end mb-0.5"
                                        title="حذف">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                @endif
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- Notes Section -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gray-800 text-white px-6 py-4">
                <h3 class="text-lg font-bold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    ملاحظات
                </h3>
            </div>
            <div class="p-6">
                @if($isShow)
                <div class="bg-gray-50 rounded-lg p-4 min-h-[100px]">
                    <p class="text-sm text-gray-900 whitespace-pre-line">{{ $studentWeeklyFollowup->notes ?? 'لا توجد ملاحظات مسجلة.' }}</p>
                </div>
                @else
                <textarea name="notes" rows="4"
                    class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm resize-none p-3"
                    placeholder="أي ملاحظات إضافية...">{{ old('notes', $studentWeeklyFollowup->notes ?? '') }}</textarea>
                @error('notes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                @endif
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- Actions -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <div class="flex justify-between items-center pt-4">
            <a href="{{ route('student-weekly-followups.index-individual') }}"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                رجوع للقائمة
            </a>

            @if(!$isShow)
            <button type="submit"
                class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-[#0a5c36] text-white font-semibold hover:bg-[#0d7a48] transition-colors shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                </svg>
                {{ $isCreate ? 'حفظ المتابعة الفردية' : 'تحديث المتابعة الفردية' }}
            </button>
            @else
            <a href="{{ route('student-weekly-followups.edit-individual', $studentWeeklyFollowup->id) }}"
                class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-amber-500 text-white font-semibold hover:bg-amber-600 transition-colors shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                تعديل
            </a>
            @endif
        </div>

        @if($isCreate || $isEdit)
</form>
@else
</div>
@endif

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- JavaScript: Alpine.js Components -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<script>
    function individualPlanForm(config) {
        return {
            circles: config.circles || [],
            allStudents: config.allStudents || [],
            selectedCircleId: config.selectedCircleId ? parseInt(config.selectedCircleId) : null,
            selectedStudentId: config.selectedStudentId ? parseInt(config.selectedStudentId) : null,
            excludedStudentIds: config.excludedStudentIds || [],
            hideCircleField: config.hideCircleField || false,
            defaultCircleId: config.defaultCircleId ? parseInt(config.defaultCircleId) : null,
            isShow: config.isShow || false,
            studentOptions: [],

            init() {
                if (this.hideCircleField && this.defaultCircleId) {
                    this.selectedCircleId = this.defaultCircleId;
                }

                if (this.selectedCircleId) {
                    this.updateStudentOptions(this.selectedCircleId);
                }

                if (this.isShow) return;

                // ✅ بدل الاعتماد على x-model عبر مكوّنين متداخلين (غير موثوق)،
                // نستمع مباشرة لحدث "searchable-change" اللي بيبثه مكوّن x-searchable-select
                // على window عند كل اختيار، ونفلتر بالاسم يدويًا
                window.addEventListener('searchable-change', (e) => {
                    if (e.detail.name === 'circle_id') {
                        const newCircleId = e.detail.value ? parseInt(e.detail.value) : null;
                        this.selectedCircleId = newCircleId;

                        if (newCircleId) {
                            this.updateStudentOptions(newCircleId);
                        } else {
                            this.studentOptions = [];
                            this.selectedStudentId = null;
                            this.dispatchUpdateOptions([]);
                        }
                    }

                    if (e.detail.name === 'student_id') {
                        this.selectedStudentId = e.detail.value ? parseInt(e.detail.value) : null;
                    }
                });
            },

            updateStudentOptions(circleId) {
                if (!circleId) return;

                const circleIdInt = parseInt(circleId);
                const circleStudents = this.allStudents.filter(s => parseInt(s.circle_id) === circleIdInt);

                // Keep current student even if in excluded list (edit mode)
                this.studentOptions = circleStudents.filter(s => {
                    const sid = parseInt(s.id);
                    return !this.excludedStudentIds.includes(sid) || sid === parseInt(this.selectedStudentId);
                });

                this.dispatchUpdateOptions(this.studentOptions);
            },

            dispatchUpdateOptions(options) {
                this.$nextTick(() => {
                    // ✅ نبعت الحدث مباشرة على window بدل البحث عن عنصر DOM محدد
                    // لأن مستمعي المكوّن (searchable-select) أصلاً مسجلين على window
                    // ده بيتفادى مشكلة عدم العثور على العنصر أو تأخر ظهور attribute "name" عليه من Alpine
                    window.dispatchEvent(new CustomEvent('update-options', {
                        detail: {
                            name: 'student_id',
                            options: options.map(s => ({
                                value: s.id,
                                label: s.name
                            })),
                            preserveSelection: true
                        }
                    }));
                });
            }
        };
    }

    function activitiesManager(config) {
        return {
            activities: config.initialActivities || [],
            isShow: config.isShow || false,

            addActivity() {
                this.activities.push({
                    activity_type: '',
                    activity_name: '',
                    activity_date: '',
                    notes: '',
                    _deleted: 'false'
                });
            },

            removeActivity(index) {
                if (this.activities[index]) {
                    this.activities[index]._deleted = 'true';
                    // Hide visually but keep in array for form submission
                    this.$nextTick(() => {
                        const el = document.querySelectorAll('.activity-row')[index];
                        if (el) el.style.display = 'none';
                    });
                }
            }
        };
    }
</script>