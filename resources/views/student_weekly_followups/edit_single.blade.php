<x-layouts.markaz-layout>
    <div class="max-w-5xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">
                    تعديل متابعة {{ $studentWeeklyFollowup->student->name ?? '' }}
                </h2>
                <p class="text-gray-500 mt-1 text-sm">
                    {{ $studentWeeklyFollowup->week_start?->format('Y-m-d') }} — {{ $studentWeeklyFollowup->week_end?->format('Y-m-d') }}
                </p>
            </div>
            <a href="{{ route('student-weekly-followups.show', $studentWeeklyFollowup) }}"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors">
                إلغاء
            </a>
        </div>

        @php
        $levels = \App\Enums\AssessmentLevel::options();
        $discipline = $studentWeeklyFollowup->discipline;
        $tajweed = $studentWeeklyFollowup->tajweedAssessment;
        $foundation = $studentWeeklyFollowup->foundationLevel;
        $eduLesson = $studentWeeklyFollowup->educationalLessonAssessment;

        $sections = [
        'new_memorization' => ['title' => 'الحفظ الجديد', 'header' => 'bg-emerald-600', 'relation' => 'newMemorizations', 'prefix' => 'memorization', 'level_name' => 'new_memorization_level'],
        'revision' => ['title' => 'المراجعة', 'header' => 'bg-sky-600', 'relation' => 'revisions', 'prefix' => 'revision', 'level_name' => 'revision_level'],
        'old_memorization' => ['title' => 'الحفظ القديم', 'header' => 'bg-amber-600', 'relation' => 'oldMemorizations', 'prefix' => 'old_revision', 'level_name' => 'old_memorization_level'],
        ];
        @endphp

        <form action="{{ route('student-weekly-followups.update', $studentWeeklyFollowup) }}" method="POST" id="weekly-followup-edit-form" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                @foreach($sections as $key => $section)
                @php
                $sectionData = $studentWeeklyFollowup->{$section['relation']};
                $prefix = $section['prefix'];
                @endphp
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="{{ $section['header'] }} text-white px-5 py-3">
                        <h3 class="text-base font-bold">{{ $section['title'] }}</h3>
                    </div>
                    <div class="p-5 space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">من سورة</label>
                            <select name="{{ $prefix }}_from_surah_id" class="w-full rounded-lg border border-gray-300 text-sm">
                                <option value="">اختر</option>
                                @foreach($surahs as $surah)
                                <option value="{{ $surah->id }}" {{ old("{$prefix}_from_surah_id", $sectionData?->plan_from_surah_id) == $surah->id ? 'selected' : '' }}>{{ $surah->name_arabic }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">من آية</label>
                            <input type="number" name="{{ $prefix }}_from_ayah" min="1" class="w-full rounded-lg border border-gray-300 text-sm" value="{{ old("{$prefix}_from_ayah", $sectionData?->plan_from_ayah) }}">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">إلى سورة</label>
                            <select name="{{ $prefix }}_to_surah_id" class="w-full rounded-lg border border-gray-300 text-sm">
                                <option value="">اختر</option>
                                @foreach($surahs as $surah)
                                <option value="{{ $surah->id }}" {{ old("{$prefix}_to_surah_id", $sectionData?->plan_to_surah_id) == $surah->id ? 'selected' : '' }}>{{ $surah->name_arabic }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">إلى آية</label>
                            <input type="number" name="{{ $prefix }}_to_ayah" min="1" class="w-full rounded-lg border border-gray-300 text-sm" value="{{ old("{$prefix}_to_ayah", $sectionData?->plan_to_ayah) }}">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">المستوى</label>
                            <select name="{{ $section['level_name'] }}" class="w-full rounded-lg border border-gray-300 text-sm">
                                <option value="">اختر</option>
                                @foreach($levels as $val => $label)
                                <option value="{{ $val }}" {{ old($section['level_name'], $sectionData?->average_level) == $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">ملاحظات</label>
                            <textarea name="{{ $prefix }}_notes" rows="2" class="w-full rounded-lg border border-gray-300 text-sm resize-none p-2">{{ old("{$prefix}_notes", $sectionData?->notes) }}</textarea>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-indigo-600 text-white px-5 py-3">
                        <h3 class="text-base font-bold">الانضباط</h3>
                    </div>
                    <div class="p-5 space-y-3">
                        <select name="discipline_level" class="w-full rounded-lg border border-gray-300 text-sm">
                            <option value="">اختر المستوى</option>
                            @foreach($levels as $val => $label)
                            <option value="{{ $val }}" {{ old('discipline_level', $discipline?->level) == $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="discipline_achievement" class="w-full rounded-lg border border-gray-300 text-sm" value="{{ old('discipline_achievement', $discipline?->notes) }}" placeholder="الإنجاز">
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-rose-600 text-white px-5 py-3">
                        <h3 class="text-base font-bold">التجويد</h3>
                    </div>
                    <div class="p-5 space-y-3">
                        <select name="tajweed_level" class="w-full rounded-lg border border-gray-300 text-sm">
                            <option value="">اختر المستوى</option>
                            @foreach($levels as $val => $label)
                            <option value="{{ $val }}" {{ old('tajweed_level', $tajweed?->level) == $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="tajweed_achievement" class="w-full rounded-lg border border-gray-300 text-sm" value="{{ old('tajweed_achievement', $tajweed?->notes) }}" placeholder="الإنجاز">
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-violet-600 text-white px-5 py-3">
                        <h3 class="text-base font-bold">مستوى التأسيس</h3>
                    </div>
                    <div class="p-5 space-y-3">
                        <select name="foundation_level_level" class="w-full rounded-lg border border-gray-300 text-sm">
                            <option value="">اختر المستوى</option>
                            @foreach($levels as $val => $label)
                            <option value="{{ $val }}" {{ old('foundation_level_level', $foundation?->level) == $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="foundation_level_achievement" class="w-full rounded-lg border border-gray-300 text-sm" value="{{ old('foundation_level_achievement', $foundation?->notes) }}" placeholder="الإنجاز">
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-800 text-white px-6 py-4">
                    <h3 class="text-lg font-bold">ملاحظات عامة</h3>
                </div>
                <div class="p-6">
                    <textarea name="notes" rows="4" class="w-full rounded-lg border border-gray-300 text-sm resize-none p-3">{{ old('notes', $studentWeeklyFollowup->notes) }}</textarea>
                </div>
            </div>

            <div class="flex justify-between items-center pt-4">
                <a href="{{ route('student-weekly-followups.show', $studentWeeklyFollowup) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">إلغاء</a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-[#0a5c36] text-white font-semibold hover:bg-[#0d7a48]">حفظ التعديلات</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('#weekly-followup-edit-form');
            if (!form) return;

            function serializeForm(formEl) {
                const data = new FormData(formEl);
                const obj = {};
                for (const [key, value] of data.entries()) {
                    if (obj[key] !== undefined) {
                        obj[key] = Array.isArray(obj[key]) ? [...obj[key], value] : [obj[key], value];
                    } else {
                        obj[key] = value;
                    }
                }
                return JSON.stringify(obj);
            }

            const initialSnapshot = serializeForm(form);

            form.addEventListener('submit', function(e) {
                const currentSnapshot = serializeForm(form);
                if (currentSnapshot === initialSnapshot) {
                    e.preventDefault();
                    alert('لم تقم بأي تعديل على البيانات.');
                }
            });
        });
    </script>
</x-layouts.markaz-layout>