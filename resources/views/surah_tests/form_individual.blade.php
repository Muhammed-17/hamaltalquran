@php
$isCreate = ($mode === 'create');
$isEdit = ($mode === 'edit');

$labelClass = 'block text-sm font-bold text-[#1e2942] mb-2';
$selectClass = 'w-full appearance-none rounded-xl border-0 bg-gray-50 pl-10 pr-4 py-3 text-sm text-gray-700 focus:ring-2 focus:ring-[#0a5c36]/40';
$inputClass = 'w-full rounded-xl border-0 bg-gray-50 px-4 py-3 text-sm text-gray-700 focus:ring-2 focus:ring-[#0a5c36]/40';
$readonlyClass = 'w-full rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600';

$levels = \App\Models\StudentSurahTestResult::LEVELS;

// صلاحية تعديل النسبة والتقدير يدويًا — لازم الكنترولر يبعتها
$canEditPercentage = $canEditPercentage ?? false;
$levelFieldStyle = $canEditPercentage ? '' : 'pointer-events:none; background-color:#f3f4f6; opacity:0.7; cursor:not-allowed;';
@endphp

<div class="bg-white rounded-2xl border border-gray-100 p-6 md:p-8 space-y-6">

    <!-- المعلم -->
    <div>
        <label class="{{ $labelClass }}">المعلم *</label>
        @if(auth()->user()->hasRole('admin'))
        <x-searchable-select
            name="teacher_id"
            :options="$teachers->map(fn($t) => ['value' => $t->id, 'label' => $t->user->name ?? $t->name])->values()"
            :defaultValue="old('teacher_id', $isEdit ? $surahTest->teacher_id : '')"
            placeholder="اختر المعلم..."
            searchPlaceholder="ابحث عن معلم..." />
        @else
        @php($currentTeacher = app(\App\Services\UserAccessService::class)->teacher(auth()->user()))
        <div class="{{ $readonlyClass }}">
            {{ $currentTeacher?->user?->name ?? $currentTeacher?->name ?? '—' }}
        </div>
        <input type="hidden" name="teacher_id" value="{{ $currentTeacher?->id }}">
        @endif
        @error('teacher_id')
        <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
        @enderror
    </div>

    <!-- الحلقة / الطالب -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="{{ $labelClass }}">الحلقة *</label>
            <x-searchable-select
                name="circle_id"
                :options="$circles->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values()"
                :defaultValue="old('circle_id', $isEdit ? $surahTest->circle_id : '')"
                placeholder="اختر الحلقة..."
                searchPlaceholder="ابحث عن حلقة..." />
            <input type="hidden" id="selected-circle-student-map"
                data-selected-student="{{ $isEdit ? ($surahTest->results->first()?->student_id ?? '') : '' }}">
            @error('circle_id')
            <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
            @enderror
        </div>

        <div id="student-select-wrapper">
            <label class="{{ $labelClass }}">الطالب *</label>
            <x-searchable-select
                name="student_id"
                :options="$isEdit && $surahTest->results->first()?->student
                    ? collect([['value' => $surahTest->results->first()->student->id, 'label' => $surahTest->results->first()->student->name]])
                    : collect([])"
                :defaultValue="old('student_id', $isEdit ? ($surahTest->results->first()?->student_id ?? '') : '')"
                placeholder="اختر الحلقة أولاً"
                searchPlaceholder="ابحث عن طالب..." />
            @error('student_id')
            <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <!-- السورة / تاريخ الاختبار -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="{{ $labelClass }}">السورة *</label>
            <x-searchable-select
                name="surah_id"
                :options="$surahs->map(fn($s) => ['value' => $s->id, 'label' => $s->name_arabic])->values()"
                :defaultValue="old('surah_id', $isEdit ? $surahTest->surah_id : '')"
                placeholder="اختر السورة..."
                searchPlaceholder="ابحث عن سورة..." />
            @error('surah_id')
            <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="{{ $labelClass }}">تاريخ الاختبار *</label>
            <input type="date" name="test_date"
                value="{{ old('test_date', $isEdit ? $surahTest->test_date?->format('Y-m-d') : now()->format('Y-m-d')) }}"
                class="{{ $inputClass }}">
            @error('test_date')
            <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <!-- ═══════════════════════════════════════ -->
    <!-- نتيجة الطالب (تظهر بعد اختيار الطالب) -->
    <!-- ═══════════════════════════════════════ -->
    @if($isCreate)
    <div id="individual-result-card" class="js-result-card bg-white rounded-2xl border border-gray-100 p-6 md:p-8" style="display:none;">
        <h3 class="text-base font-bold text-[#1e2942] mb-6">نتيجة الاختبار</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="{{ $labelClass }}">عدد أخطاء الفتح</label>
                <input type="number" min="0" name="results[0][prompt_errors]" value="{{ old('results.0.prompt_errors', 0) }}"
                    class="js-prompt-errors {{ $inputClass }}">
                @error('results.0.prompt_errors')
                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">عدد الأخطاء التشكيلية</label>
                <input type="number" min="0" name="results[0][tashkeel_errors]" value="{{ old('results.0.tashkeel_errors', 0) }}"
                    class="js-tashkeel-errors {{ $inputClass }}">
                @error('results.0.tashkeel_errors')
                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">النسبة %</label>
                <input type="number" min="0" max="100" name="results[0][percentage]" value="{{ old('results.0.percentage', 100) }}"
                    class="js-percentage {{ $inputClass }}"
                    @if(!$canEditPercentage) readonly @endif>
                @error('results.0.percentage')
                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">التقدير</label>
                <div class="relative">
                    <select name="results[0][level]" class="js-level {{ $selectClass }}"
                        @if(!$canEditPercentage) style="{{ $levelFieldStyle }}" tabindex="-1" @endif>
                        <option value="">-- بدون تقدير --</option>
                        @foreach($levels as $lvl)
                        <option value="{{ $lvl }}" {{ old('results.0.level') == $lvl ? 'selected' : '' }}>
                            {{ $lvl }}
                        </option>
                        @endforeach
                    </select>
                    <svg class="w-4 h-4 text-gray-400 absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
                @error('results.0.level')
                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                @enderror
            </div>
            <div class="md:col-span-2">
                <label class="{{ $labelClass }}">ملاحظات النتيجة</label>
                <input type="text" name="results[0][notes]" value="{{ old('results.0.notes') }}"
                    class="{{ $inputClass }}">
                @error('results.0.notes')
                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <input type="hidden" name="results[0][student_id]" id="individual-result-student-id" value="{{ old('results.0.student_id') }}">
        @error('results.0.student_id')
        <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
        @enderror
    </div>
    @endif

    <!-- ═══════════════════════════════════════ -->
    <!-- نتيجة الطالب القابلة للتعديل -->
    <!-- ═══════════════════════════════════════ -->
    @if($isEdit)
    @php($result = $surahTest->results->first())
    <div class="bg-white rounded-2xl border border-gray-100 p-6 md:p-8">
        <h3 class="text-base font-bold text-[#1e2942] mb-6">نتيجة الاختبار</h3>
        @if($result)
        <input type="hidden" name="results[0][id]" value="{{ $result->id }}">
        <div class="js-result-card grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="{{ $labelClass }}">عدد أخطاء الفتح</label>
                <input type="number" min="0" name="results[0][prompt_errors]"
                    value="{{ old('results.0.prompt_errors', $result->prompt_errors) }}"
                    class="js-prompt-errors {{ $inputClass }}">
                @error('results.0.prompt_errors')
                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">عدد الأخطاء التشكيلية</label>
                <input type="number" min="0" name="results[0][tashkeel_errors]"
                    value="{{ old('results.0.tashkeel_errors', $result->tashkeel_errors) }}"
                    class="js-tashkeel-errors {{ $inputClass }}">
                @error('results.0.tashkeel_errors')
                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">النسبة %</label>
                <input type="number" min="0" max="100" name="results[0][percentage]"
                    value="{{ old('results.0.percentage', $result->percentage) }}"
                    class="js-percentage {{ $inputClass }}"
                    @if(!$canEditPercentage) readonly @endif>
                @error('results.0.percentage')
                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">التقدير</label>
                <div class="relative">
                    <select name="results[0][level]" class="js-level {{ $selectClass }}"
                        @if(!$canEditPercentage) style="{{ $levelFieldStyle }}" tabindex="-1" @endif>
                        <option value="">-- بدون تقدير --</option>
                        @foreach($levels as $lvl)
                        <option value="{{ $lvl }}" {{ old('results.0.level', $result->level) == $lvl ? 'selected' : '' }}>
                            {{ $lvl }}
                        </option>
                        @endforeach
                    </select>
                    <svg class="w-4 h-4 text-gray-400 absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
                @error('results.0.level')
                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                @enderror
            </div>
            <div class="md:col-span-2">
                <label class="{{ $labelClass }}">ملاحظات النتيجة</label>
                <input type="text" name="results[0][notes]"
                    value="{{ old('results.0.notes', $result->notes) }}"
                    class="{{ $inputClass }}">
                @error('results.0.notes')
                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                @enderror
            </div>
        </div>
        @else
        <p class="text-sm text-gray-400 text-center py-6">لا توجد نتيجة مسجلة لهذا الاختبار.</p>
        @endif
    </div>
    @endif
    <script>
        // القيم جايه مباشرة من StudentSurahTestResult::LEVELS لتفادي أي اختلاف في الكتابة
        const LEVELS_LIST = @json($levels);

        // السورة المختارة حاليًا
        let currentSurahId = @json(old('surah_id', $isEdit ? ($surahTest->surah_id ?? null) : null));

        // تتبّع تغيير السورة (الحدث بيتبعت من x-searchable-select في select() و clearSelection())
        window.addEventListener('searchable-change', function(e) {
            if (e.detail.name === 'surah_id') {
                currentSurahId = e.detail.value;
                recalculateAllCards();
            }
        });

        // تتبّع تغيير عدد الأخطاء
        document.addEventListener('input', function(e) {
            if (!e.target.matches('.js-prompt-errors, .js-tashkeel-errors')) return;

            const card = e.target.closest('.js-result-card');
            if (!card) return;

            calculateCardResult(card);
        });

        function recalculateAllCards() {
            document.querySelectorAll('.js-result-card').forEach(calculateCardResult);
        }

        // أوزان الخصم: من يس (36) إلى الناس (114) = فتح 1 / تشكيل 0.5، غير كده = فتح 2 / تشكيل 1
        function getWeights(surahId) {
            const id = parseInt(surahId, 10);

            // الفاتحة (1) أو من يس (36) إلى الناس (114): الفتح = 1، التشكيل = 0.5
            if (!isNaN(id) && (id === 1 || (id >= 36 && id <= 114))) {
                return {
                    prompt: 1,
                    tashkeel: 0.5
                };
            }

            // باقي السور (الافتراضي): الفتح = 2، التشكيل = 1
            return {
                prompt: 2,
                tashkeel: 1
            };
        }

        function calculateCardResult(card) {
            const promptInput = card.querySelector('.js-prompt-errors');
            const tashkeelInput = card.querySelector('.js-tashkeel-errors');
            const percentageInput = card.querySelector('.js-percentage');
            const levelSelect = card.querySelector('.js-level');

            const promptErrors = parseFloat(promptInput?.value) || 0;
            const tashkeelErrors = parseFloat(tashkeelInput?.value) || 0;

            const weights = getWeights(currentSurahId);

            let percentage = 100 - (promptErrors * weights.prompt) - (tashkeelErrors * weights.tashkeel);
            percentage = Math.max(0, Math.min(100, percentage));

            if (percentageInput) {
                percentageInput.value = percentage;
            }

            if (levelSelect) {
                const level = getLevelFromPercentage(percentage);
                const optionExists = Array.from(levelSelect.options).some(o => o.value === level);
                if (optionExists) {
                    levelSelect.value = level;
                }
            }
        }

        function getLevelFromPercentage(percentage) {
            if (percentage >= 95) return LEVELS_LIST[0]; // ممتاز
            if (percentage >= 90) return LEVELS_LIST[1]; // جيد جداً
            if (percentage >= 85) return LEVELS_LIST[2]; // جيد
            if (percentage >= 80) return LEVELS_LIST[3]; // مقبول
            return LEVELS_LIST[4]; // إعادة
        }
    </script>