@php
$isShow = ($mode === 'show');
$isEdit = ($mode === 'edit');
$isCreate = ($mode === 'create');
$readonly = $isShow ? 'readonly disabled' : '';

$formAction = $isCreate
? route('student-weekly-followups.store-group')
: ($isEdit ? route('student-weekly-followups.update-group', $batchId) : '');

$assessmentLevels = ['ممتاز' => 'ممتاز', 'جيد جداً' => 'جيد جداً', 'جيد' => 'جيد', 'مقبول' => 'مقبول', 'ضعيف' => 'ضعيف'];
$activityTypes = ['معرفي' => 'معرفي/علمي', 'اجتماعي' => 'اجتماعي', 'ترفيهي' => 'ترفيهي', 'إيماني' => 'إيماني/دعوي',];
@endphp

@if($isCreate || $isEdit)
<form action="{{ $formAction }}" method="POST" id="followup-form" class="space-y-6">
    @csrf
    @if($isEdit)
    @method('PUT')
    @endif
    @endif

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- Week Data Section -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-[#0a5c36] text-white px-6 py-4">
            <h3 class="text-lg font-bold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                بيانات الأسبوع
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الحلقة</label>
                    @if($isShow)
                    <div class="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-2.5 text-sm text-gray-700">
                        {{ $circles->firstWhere('id', old('circle_id', $plan_data['circle_id'] ?? $selectedCircleId ?? ''))?->name ?? '—' }}
                    </div>
                    <input type="hidden" name="circle_id" value="{{ old('circle_id', $plan_data['circle_id'] ?? $selectedCircleId ?? '') }}">
                    @else
                    <x-searchable-select
                        name="circle_id"
                        :options="$circles->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values()"
                        :defaultValue="old('circle_id', $plan_data['circle_id'] ?? $selectedCircleId ?? '')"
                        placeholder="اختر الحلقة"
                        searchPlaceholder="ابحث عن حلقة..." />
                    @error('circle_id')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">بداية الأسبوع</label>
                    <input type="date" name="week_start"
                        class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36]"
                        value="{{ old('week_start', $plan_data['week_start'] ?? $weekDates['week_start'] ?? '') }}"
                        {{ $readonly }}>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نهاية الأسبوع</label>
                    <input type="date" name="week_end"
                        class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36]"
                        value="{{ old('week_end', $plan_data['week_end'] ?? $weekDates['week_end'] ?? '') }}"
                        {{ $readonly }}>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">المعلم</label>
                    @if($isShow)
                    <div class="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-2.5 text-sm text-gray-700">
                        {{ $teachers->firstWhere('id', old('teacher_id', $plan_data['teacher_id'] ?? ''))?->user?->name
                            ?? $teachers->firstWhere('id', old('teacher_id', $plan_data['teacher_id'] ?? ''))?->name
                            ?? '—' }}
                    </div>
                    <input type="hidden" name="teacher_id" value="{{ old('teacher_id', $plan_data['teacher_id'] ?? '') }}">
                    @else
                    <x-searchable-select
                        name="teacher_id"
                        :options="$teachers->map(fn($t) => ['value' => $t->id, 'label' => $t->user->name ?? $t->name])->values()"
                        :defaultValue="old('teacher_id', $plan_data['teacher_id'] ?? '')"
                        placeholder="اختر المعلم"
                        searchPlaceholder="ابحث عن معلم..." />
                    @error('teacher_id')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                    @endif
                </div>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">أيام الدراسة</label>
                <div class="flex flex-wrap gap-3">
                    @php
                    $studyDays = old('study_days', $plan_data['study_days'] ?? []);
                    $days = [
                    'Saturday' => 'السبت', 'Sunday' => 'الأحد', 'Monday' => 'الإثنين',
                    'Tuesday' => 'الثلاثاء', 'Wednesday' => 'الأربعاء',
                    'Thursday' => 'الخميس', 'Friday' => 'الجمعة'
                    ];
                    @endphp
                    @foreach($days as $en => $ar)
                    <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50 transition-colors has-checked:bg-[#0a5c36]/5 has-checked:border-[#0a5c36]">
                        <input type="checkbox" name="study_days[]" value="{{ $en }}"
                            class="rounded border-gray-300 text-[#0a5c36] focus:ring-[#0a5c36]"
                            {{ in_array($en, $studyDays) ? 'checked' : '' }} {{ $readonly }}>
                        <span class="text-sm text-gray-700">{{ $ar }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- Plan Sections: New Memorization, Revision, Old Memorization -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    @php
    $sections = [
    'new_memorization' => [
    'title' => 'الحفظ الجديد',
    'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
    'header' => 'bg-emerald-600',
    ],
    'revision' => [
    'title' => 'المراجعة',
    'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
    'header' => 'bg-sky-600',
    ],
    'old_memorization' => [
    'title' => 'الحفظ القديم',
    'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
    'header' => 'bg-amber-600',
    ],
    ];
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @foreach($sections as $key => $section)
        @php
        $sectionData = old($key, $plan_data[$key] ?? []);
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
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">من سورة</label>
                        @if($isShow)
                        <div class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                            {{ $surahs->firstWhere('id', $sectionData['from_surah_id'] ?? '')?->name_arabic ?? '—' }}
                        </div>
                        <input type="hidden" name="{{ $key }}[from_surah_id]" value="{{ $sectionData['from_surah_id'] ?? '' }}">
                        @else
                        <x-searchable-select
                            :name="$key.'[from_surah_id]'"
                            :options="$surahs->map(fn($s) => ['value' => $s->id, 'label' => $s->name_arabic])->values()"
                            :defaultValue="$sectionData['from_surah_id'] ?? ''"
                            placeholder="اختر"
                            searchPlaceholder="ابحث عن سورة..." />
                        @endif
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">من آية</label>
                        <input type="number" name="{{ $key }}[from_ayah]"
                            class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm"
                            value="{{ $sectionData['from_ayah'] ?? '' }}" min="1" {{ $readonly }}>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">إلى سورة</label>
                        @if($isShow)
                        <div class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                            {{ $surahs->firstWhere('id', $sectionData['to_surah_id'] ?? '')?->name_arabic ?? '—' }}
                        </div>
                        <input type="hidden" name="{{ $key }}[to_surah_id]" value="{{ $sectionData['to_surah_id'] ?? '' }}">
                        @else
                        <x-searchable-select
                            :name="$key.'[to_surah_id]'"
                            :options="$surahs->map(fn($s) => ['value' => $s->id, 'label' => $s->name_arabic])->values()"
                            :defaultValue="$sectionData['to_surah_id'] ?? ''"
                            placeholder="اختر"
                            searchPlaceholder="ابحث عن سورة..." />
                        @endif
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">إلى آية</label>
                        <input type="number" name="{{ $key }}[to_ayah]"
                            class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm"
                            value="{{ $sectionData['to_ayah'] ?? '' }}" min="1" {{ $readonly }}>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">مقارنة الخطة</label>
                        <select name="{{ $key }}[plan_comparison]"
                            class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm"
                            {{ $readonly }}>
                            <option value="">اختر</option>
                            @foreach(['مطابق' => 'مطابق', 'متقدم' => 'متقدم', 'متأخر' => 'متأخر'] as $val => $label)
                            <option value="{{ $val }}" {{ ($sectionData['plan_comparison'] ?? '') == $val ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">فرق التقدم</label>
                        <input type="text" name="{{ $key }}[progress_difference]"
                            class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm"
                            value="{{ $sectionData['progress_difference'] ?? '' }}" {{ $readonly }}>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">ملاحظات</label>
                    <textarea name="{{ $key }}[notes]" rows="2"
                        class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm resize-none"
                        placeholder="أي ملاحظات إضافية..." {{ $readonly }}>{{ $sectionData['notes'] ?? '' }}</textarea>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- Other Assessment Sections: Discipline, Tajweed, Foundation, Educational -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Discipline -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 text-white px-5 py-3">
                <h3 class="text-base font-bold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    الانضباط
                </h3>
            </div>
            <div class="p-5">
                <label class="block text-sm font-medium text-gray-700 mb-2">الإنجاز</label>
                <textarea name="discipline_achievement" rows="4"
                    class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm resize-none p-3"
                    placeholder="اكتب ما تم إنجازه في الانضباط..." {{ $readonly }}>{{ old('discipline_achievement', $plan_data['discipline_achievement'] ?? '') }}</textarea>
            </div>
        </div>

        <!-- Tajweed -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 text-white px-5 py-3">
                <h3 class="text-base font-bold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                    </svg>
                    التجويد
                </h3>
            </div>
            <div class="p-5">
                <label class="block text-sm font-medium text-gray-700 mb-2">الإنجاز</label>
                <textarea name="tajweed_achievement" rows="4"
                    class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm resize-none p-3"
                    placeholder="اكتب ما تم إنجازه في التجويد..." {{ $readonly }}>{{ old('tajweed_achievement', $plan_data['tajweed_achievement'] ?? '') }}</textarea>
            </div>
        </div>

        <!-- Foundation Level -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 text-white px-5 py-3">
                <h3 class="text-base font-bold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                    مستوى التأسيس
                </h3>
            </div>
            <div class="p-5">
                <label class="block text-sm font-medium text-gray-700 mb-2">الإنجاز</label>
                <textarea name="foundation_level_achievement" rows="4"
                    class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm resize-none p-3"
                    placeholder="اكتب ما تم إنجازه في مستوى التأسيس..." {{ $readonly }}>{{ old('foundation_level_achievement', $plan_data['foundation_level_achievement'] ?? '') }}</textarea>
            </div>
        </div>
    </div>

    <!-- Educational Lesson -->
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
            @php
            $currentLessonId = old('educational_lesson_id', $plan_data['educational_lesson_id'] ?? ($educationalLesson->id ?? null));
            $displayLesson = $educationalLesson ?? ($plan_data['educational_lesson'] ?? null);
            @endphp
            @if($displayLesson)
            <input type="hidden" name="educational_lesson_id" value="{{ $currentLessonId }}">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">عنوان الدرس</label>
                <p class="text-sm font-bold text-gray-900">{{ $displayLesson->title }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الوصف</label>
                <p class="text-sm text-gray-600">{{ $displayLesson->description }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
                <textarea name="educational_lesson_achievement" rows="2"
                    class="w-full rounded-lg border border-gray-300 text-sm resize-none p-2">{{ old('educational_lesson_achievement', $plan_data['educational_lesson_achievement'] ?? null) }}</textarea>
            </div>
            @else
            <div class="bg-amber-50 rounded-lg p-3 border border-amber-200">
                <p class="text-xs text-amber-700 text-center">لا يوجد درس تربوي مسجل.</p>
            </div>
            @endif
        </div>
    </div>
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- STUDENT ACTIVITIES SECTION -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <!-- Header - دائماً ظاهر وخارج Alpine.js -->
        <div class="bg-purple-600 text-white px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-bold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                الأنشطة الطلابية
            </h3>
            @if(!$isShow)
            <button type="button" onclick="document.getElementById('activities-container').dispatchEvent(new CustomEvent('add-activity', { bubbles: true }))"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/20 text-white text-sm font-medium hover:bg-white/30 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                إضافة نشاط
            </button>
            @endif
        </div>

        <!-- Content - مع Alpine.js منفصل -->
        <div class="p-6"
            id="activities-container"
            x-data="activitiesManager({{ json_encode(old('activities', $activities_data ?? [])) }})"
            x-init="init()"
            @add-activity.window="addActivity()">

            <!-- Activities List -->
            <div class="space-y-6">
                <template x-for="(activity, index) in activities" :key="activity.key">
                    <div class="relative border border-gray-200 rounded-xl p-5 bg-gray-50/50"
                        :class="activity._deleted ? 'opacity-50' : ''">
                        <!-- Activity Header -->
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-sm font-bold text-gray-800">
                                النشاط <span x-text="index + 1"></span>
                            </h4>
                            @if(!$isShow)
                            <button type="button" @click="removeActivity(index)"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-red-50 text-red-600 text-xs font-medium hover:bg-red-100 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                حذف
                            </button>
                            @endif
                        </div>
                        <!-- Activity Fields -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">نوع النشاط</label>
                                <select :name="`activities[${index}][activity_type]`" x-model="activity.activity_type"
                                    class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm"
                                    {{ $readonly }}>
                                    <option value="">اختر النوع</option>
                                    @foreach($activityTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">اسم النشاط</label>
                                <input type="text" :name="`activities[${index}][activity_name]`" x-model="activity.activity_name"
                                    class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm"
                                    placeholder="اسم النشاط" {{ $readonly }}>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">تاريخ النشاط</label>
                                <input type="date" :name="`activities[${index}][activity_date]`" x-model="activity.activity_date"
                                    class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm"
                                    {{ $readonly }}>
                            </div>
                            <div class="md:col-span-2 lg:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">ملاحظات</label>
                                <input type="text" :name="`activities[${index}][notes]`" x-model="activity.notes"
                                    class="w-full rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm"
                                    placeholder="ملاحظات..." {{ $readonly }}>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Empty State -->
            <div x-show="activities.length === 0" class="text-center py-8 text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm">لا توجد أنشطة مسجلة</p>
                @if(!$isShow)
                <button type="button" @click="addActivity()"
                    class="mt-3 inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-purple-50 text-purple-600 text-sm font-medium hover:bg-purple-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    إضافة نشاط جديد
                </button>
                @endif
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- Quick Actions Section -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    @if(!$isShow)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" x-data="quickActions()">
        <div class="bg-gray-800 text-white px-6 py-4">
            <h3 class="text-lg font-bold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                إجراءات سريعة
            </h3>
            <p class="text-xs text-gray-300 mt-1">اختر قيمة ثم اضغط "تطبيق" لتحديث جميع الطلاب في نفس العمود</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach([
                ['key' => 'new_memorization_level', 'label' => 'الحفظ الجديد'],
                ['key' => 'revision_level', 'label' => 'المراجعة'],
                ['key' => 'old_memorization_level', 'label' => 'الحفظ القديم'],
                ['key' => 'discipline_level', 'label' => 'الانضباط'],
                ['key' => 'tajweed_level', 'label' => 'التجويد'],
                ['key' => 'educational_lesson_level', 'label' => 'الدرس التربوي'],
                ['key' => 'foundation_level_level', 'label' => 'مستوى التأسيس'],
                ] as $action)
                <div class="flex items-center gap-2">
                    <select x-ref="{{ $action['key'] }}" id="quick-{{ $action['key'] }}"
                        class="flex-1 rounded-lg border border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm">
                        <option value="">{{ $action['label'] }}</option>
                        @foreach($assessmentLevels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="button"
                        @click="applyToAll('{{ $action['key'] }}', $refs['{{ $action['key'] }}'].value)"
                        class="px-3 py-2 rounded-lg bg-[#0a5c36] text-white text-xs font-medium hover:bg-[#084a2c] transition-colors whitespace-nowrap">
                        تطبيق
                    </button>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- Students Assessment Section -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-gray-800 text-white px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-bold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                متابعة الطلاب
                <span id="students-count-badge" class="text-sm font-normal text-gray-300">({{ count($students ?? []) }} طالب)</span>
            </h3>
        </div>
        @error('students')
        <div class="bg-red-50 border-b border-red-200 px-6 py-3">
            <p class="text-sm text-red-600">{{ $message }}</p>
        </div>
        @enderror
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="students-table">
                <thead class="bg-gray-100 text-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-right font-semibold min-w-35">الطالب</th>
                        <th class="px-3 py-3 text-center font-semibold min-w-27.5">الانضباط</th>
                        <th class="px-3 py-3 text-center font-semibold min-w-27.5">التجويد</th>
                        <th class="px-3 py-3 text-center font-semibold min-w-27.5">الدرس التربوي</th>
                        <th class="px-3 py-3 text-center font-semibold min-w-27.5">مستوى التأسيس</th>
                        <th class="px-3 py-3 text-center font-semibold min-w-27.5">الحفظ الجديد</th>
                        <th class="px-3 py-3 text-center font-semibold min-w-27.5">المراجعة</th>
                        <th class="px-3 py-3 text-center font-semibold min-w-27.5">الحفظ القديم</th>
                        <th class="px-4 py-3 text-right font-semibold min-w-40">الملاحظات العامة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @if($isCreate && (!isset($students) || $students->isEmpty()))
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            @if(!$selectedCircleId)
                            <p class="text-sm font-medium text-amber-600">اختر حلقة أولاً من الأعلى لعرض طلابها</p>
                            @else
                            <p class="text-sm">لا يوجد طلاب في هذه الحلقة</p>
                            @endif
                        </td>
                    </tr>
                    @else
                    @foreach($students as $index => $student)
                    @php
                    $studentData = collect($students_data ?? [])->firstWhere('student_id', $student->id);
                    $oldPrefix = "students.{$index}";
                    $disciplineLevel = old("{$oldPrefix}.discipline_level", $studentData['discipline_level'] ?? '');
                    $tajweedLevel = old("{$oldPrefix}.tajweed_level", $studentData['tajweed_level'] ?? '');
                    $eduLevel = old("{$oldPrefix}.educational_lesson_level", $studentData['educational_lesson_level'] ?? '');
                    $foundationLevel = old("{$oldPrefix}.foundation_level_level", $studentData['foundation_level_level'] ?? '');
                    $newMemLevel = old("{$oldPrefix}.new_memorization_level", $studentData['new_memorization_level'] ?? '');
                    $revLevel = old("{$oldPrefix}.revision_level", $studentData['revision_level'] ?? '');
                    $oldMemLevel = old("{$oldPrefix}.old_memorization_level", $studentData['old_memorization_level'] ?? '');
                    $generalNotes = old("{$oldPrefix}.general_notes", $studentData['general_notes'] ?? '');
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors" data-student-id="{{ $student->id }}">
                        <!-- Student Name -->
                        <td class="px-4 py-3">
                            <input type="hidden" name="students[{{ $index }}][student_id]" value="{{ $student->id }}">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-[#0a5c36]/10 text-[#0a5c36] flex items-center justify-center text-xs font-bold">
                                    {{ $index + 1 }}
                                </div>
                                <span class="font-medium text-gray-900">{{ $student->name }}</span>
                            </div>
                        </td>

                        <!-- Discipline -->
                        <td class="px-3 py-3">
                            <select name="students[{{ $index }}][discipline_level]"
                                class="student-discipline_level w-full rounded-lg border border-gray-300 text-sm focus:border-[#0a5c36] focus:ring-[#0a5c36]"
                                {{ $readonly }}>
                                <option value="">--</option>
                                @foreach($assessmentLevels as $value => $label)
                                <option value="{{ $value }}" {{ $disciplineLevel == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>

                        <!-- Tajweed -->
                        <td class="px-3 py-3">
                            <select name="students[{{ $index }}][tajweed_level]"
                                class="student-tajweed_level w-full rounded-lg border border-gray-300 text-sm focus:border-[#0a5c36] focus:ring-[#0a5c36]"
                                {{ $readonly }}>
                                <option value="">--</option>
                                @foreach($assessmentLevels as $value => $label)
                                <option value="{{ $value }}" {{ $tajweedLevel == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>

                        <!-- Educational Lesson -->
                        <td class="px-3 py-3">
                            <select name="students[{{ $index }}][educational_lesson_level]"
                                class="student-educational_lesson_level w-full rounded-lg border border-gray-300 text-sm focus:border-[#0a5c36] focus:ring-[#0a5c36]"
                                {{ $readonly }}>
                                <option value="">--</option>
                                @foreach($assessmentLevels as $value => $label)
                                <option value="{{ $value }}" {{ $eduLevel == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>

                        <!-- Foundation Level -->
                        <td class="px-3 py-3">
                            <select name="students[{{ $index }}][foundation_level_level]"
                                class="student-foundation_level_level w-full rounded-lg border border-gray-300 text-sm focus:border-[#0a5c36] focus:ring-[#0a5c36]"
                                {{ $readonly }}>
                                <option value="">--</option>
                                @foreach($assessmentLevels as $value => $label)
                                <option value="{{ $value }}" {{ $foundationLevel == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>

                        <!-- New Memorization -->
                        <td class="px-3 py-3">
                            <select name="students[{{ $index }}][new_memorization_level]"
                                class="student-new_memorization_level w-full rounded-lg border border-gray-300 text-sm focus:border-[#0a5c36] focus:ring-[#0a5c36]"
                                {{ $readonly }}>
                                <option value="">--</option>
                                @foreach($assessmentLevels as $value => $label)
                                <option value="{{ $value }}" {{ $newMemLevel == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>

                        <!-- Revision -->
                        <td class="px-3 py-3">
                            <select name="students[{{ $index }}][revision_level]"
                                class="student-revision_level w-full rounded-lg border border-gray-300 text-sm focus:border-[#0a5c36] focus:ring-[#0a5c36]"
                                {{ $readonly }}>
                                <option value="">--</option>
                                @foreach($assessmentLevels as $value => $label)
                                <option value="{{ $value }}" {{ $revLevel == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>

                        <!-- Old Memorization -->
                        <td class="px-3 py-3">
                            <select name="students[{{ $index }}][old_memorization_level]"
                                class="student-old_memorization_level w-full rounded-lg border border-gray-300 text-sm focus:border-[#0a5c36] focus:ring-[#0a5c36]"
                                {{ $readonly }}>
                                <option value="">--</option>
                                @foreach($assessmentLevels as $value => $label)
                                <option value="{{ $value }}" {{ $oldMemLevel == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>

                        <!-- General Notes -->
                        <td class="px-4 py-3">
                            <textarea name="students[{{ $index }}][general_notes]" rows="1"
                                class="w-full rounded-lg border border-gray-300 text-sm focus:border-[#0a5c36] focus:ring-[#0a5c36] resize-none"
                                placeholder="ملاحظات..." {{ $readonly }}>{{ $generalNotes }}</textarea>
                        </td>
                    </tr>
                    @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex justify-between items-center pt-4">
        <a href="{{ route('student-weekly-followups.index-group') }}"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            رجوع
        </a>

        @if(!$isShow)
        <button type="submit"
            class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-[#0a5c36] text-white font-semibold hover:bg-[#0d7a48] transition-colors shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            {{ $isCreate ? 'حفظ المتابعة الجماعية' : 'تحديث المتابعة الجماعية' }}
        </button>
        @else
        <a href="{{ route('student-weekly-followups.edit-group', $batchId) }}"
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
@endif

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- JavaScript: Alpine.js Components -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<script>
    window.addEventListener('searchable-change', (e) => {
        if (e.detail.name === 'circle_id' && e.detail.value) {
            loadStudentsForCircle(e.detail.value);
        }
    });

    function loadStudentsForCircle(circleId) {
        const tbody = document.querySelector('#students-table tbody');
        const countBadge = document.querySelector('#students-count-badge');

        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="px-6 py-12 text-center text-gray-400">
                    <p class="text-sm">جاري تحميل الطلاب...</p>
                </td>
            </tr>
        `;

        fetch(`/student-weekly-followups/group/circles/${circleId}/students`)
            .then(res => res.json())
            .then(students => {
                if (countBadge) countBadge.textContent = `(${students.length} طالب)`;

                if (students.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-gray-400">
                                <p class="text-sm">لا يوجد طلاب في هذه الحلقة</p>
                            </td>
                        </tr>
                    `;
                    return;
                }

                tbody.innerHTML = students.map((student, index) => buildStudentRow(student, index)).join('');
            })
            .catch(() => {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-red-500">
                            <p class="text-sm">حدث خطأ أثناء تحميل الطلاب</p>
                        </td>
                    </tr>
                `;
            });
    }

    function buildStudentRow(student, index) {
        const levelOptions = ['ممتاز', 'جيد جداً', 'جيد', 'مقبول', 'ضعيف'];
        const buildSelect = (fieldKey) => `
            <select name="students[${index}][${fieldKey}]" class="student-${fieldKey} w-full rounded-lg border border-gray-300 text-sm focus:border-[#0a5c36] focus:ring-[#0a5c36]">
                <option value="">--</option>
                ${levelOptions.map(l => `<option value="${l}">${l}</option>`).join('')}
            </select>
        `;

        return `
            <tr class="hover:bg-gray-50 transition-colors" data-student-id="${student.id}">
                <td class="px-4 py-3">
                    <input type="hidden" name="students[${index}][student_id]" value="${student.id}">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-[#0a5c36]/10 text-[#0a5c36] flex items-center justify-center text-xs font-bold">
                            ${index + 1}
                        </div>
                        <span class="font-medium text-gray-900">${student.name}</span>
                    </div>
                </td>
                <td class="px-3 py-3">${buildSelect('discipline_level')}</td>
                <td class="px-3 py-3">${buildSelect('tajweed_level')}</td>
                <td class="px-3 py-3">${buildSelect('educational_lesson_level')}</td>
                <td class="px-3 py-3">${buildSelect('foundation_level_level')}</td>
                <td class="px-3 py-3">${buildSelect('new_memorization_level')}</td>
                <td class="px-3 py-3">${buildSelect('revision_level')}</td>
                <td class="px-3 py-3">${buildSelect('old_memorization_level')}</td>
                <td class="px-4 py-3">
                    <textarea name="students[${index}][general_notes]" rows="1" class="w-full rounded-lg border border-gray-300 text-sm focus:border-[#0a5c36] focus:ring-[#0a5c36] resize-none" placeholder="ملاحظات..."></textarea>
                </td>
            </tr>
        `;
    }
    // Activities Manager
    function activitiesManager(initialActivities) {
        return {
            activities: [],
            nextKey: 0,

            init() {
                if (initialActivities && initialActivities.length > 0) {
                    this.activities = initialActivities.map((a, i) => ({
                        key: i,
                        id: a.id || null,
                        activity_type: a.activity_type || '',
                        activity_name: a.activity_name || '',
                        activity_date: a.activity_date || '',
                        notes: a.notes || '',
                        _deleted: false,
                    }));
                    this.nextKey = initialActivities.length;
                }
            },

            addActivity() {
                this.activities.push({
                    key: this.nextKey++,
                    id: null,
                    activity_type: '',
                    activity_name: '',
                    activity_date: '',
                    notes: '',
                    _deleted: false,
                });
            },

            removeActivity(index) {
                this.activities.splice(index, 1);
            },
        };
    }

    // Quick Actions
    function quickActions() {
        return {
            applyToAll(fieldName, value) {
                if (!value) {
                    showError('يرجى اختيار قيمة أولاًّ');
                    return;
                }
                const selects = document.querySelectorAll('.student-' + fieldName);
                let count = 0;
                selects.forEach(select => {
                    select.value = value;
                    select.dispatchEvent(new Event('change'));
                    count++;
                });
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = 'تم (' + count + ')';
                btn.classList.add('bg-green-700');
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.classList.remove('bg-green-700');
                }, 1500);
            }
        };
    }
</script>