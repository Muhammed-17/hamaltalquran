@php
$isCreate = ($mode === 'create');
$isEdit = ($mode === 'edit');

$labelClass = 'block text-sm font-bold text-[#1e2942] mb-2';
$selectClass = 'w-full appearance-none rounded-xl border-0 bg-gray-50 pl-10 pr-4 py-3 text-sm text-gray-700 focus:ring-2 focus:ring-[#0a5c36]/40';
$inputClass = 'w-full rounded-xl border-0 bg-gray-50 px-4 py-3 text-sm text-gray-700 focus:ring-2 focus:ring-[#0a5c36]/40';
$readonlyClass = 'w-full rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600';

$fieldBoxClass = 'w-full rounded-xl border border-gray-100 bg-gray-50 text-center py-2 text-sm font-bold text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#0a5c36]/40';
$fieldLabelClass = 'text-[11px] font-semibold text-gray-400 mt-1.5 text-center';

$levels = \App\Models\StudentSurahTestResult::LEVELS;

$results = $isEdit ? $surahTest->results : collect();

// صلاحية تعديل النسبة والتقدير يدويًا — لازم الكنترولر يبعتها
$canEditPercentage = $canEditPercentage ?? false;

// كلاس إضافي يتحط على حقل النسبة لو المستخدم مالوش صلاحية
$percentageFieldClass = $canEditPercentage ? '' : 'bg-gray-100! cursor-not-allowed!';
$levelFieldStyle = $canEditPercentage ? '' : 'pointer-events:none; background-color:#f3f4f6; opacity:0.7; cursor:not-allowed;';
@endphp

<div class="bg-white rounded-2xl border border-gray-100 p-6 md:p-8 space-y-6">

    <!-- المعلم -->
    <div>
        <label class="{{ $labelClass }}">المعلم *</label>
        @if(auth()->user()->hasRole(['admin', 'general_manager']))
        <x-searchable-select
            name="teacher_id"
            :options="$teachers->map(fn($t) => ['value' => $t->id, 'label' => $t->user->name ?? $t->name])->values()->toArray()"
            :default-value="old('teacher_id', $isEdit ? $surahTest->teacher_id : '')"
            placeholder="اختر المعلم..."
            search-placeholder="ابحث عن معلم..." />
        @else
        @php($currentTeacher = app(\App\Services\UserAccessService::class)->teacher(auth()->user()))
        <div class="{{ $readonlyClass }}">
            {{ auth()->user()->name }}
        </div>
        <input type="hidden" name="teacher_id" value="{{ $currentTeacher?->id }}">
        @endif
        @error('teacher_id')
        <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
        @enderror
    </div>

    <!-- الحلقة / السورة -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="{{ $labelClass }}">الحلقة *</label>
            <x-searchable-select
                name="circle_id"
                :options="$circles->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values()->toArray()"
                :default-value="old('circle_id', $isEdit ? $surahTest->circle_id : '')"
                placeholder="اختر الحلقة..."
                search-placeholder="ابحث عن حلقة..." />
            @error('circle_id')
            <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="{{ $labelClass }}">السورة *</label>
            <x-searchable-select
                name="surah_id"
                :options="$surahs->map(fn($s) => ['value' => $s->id, 'label' => $s->name_arabic])->values()->toArray()"
                :default-value="old('surah_id', $isEdit ? $surahTest->surah_id : '')"
                placeholder="اختر السورة..."
                search-placeholder="ابحث عن سورة..." />
            @error('surah_id')
            <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <!-- تاريخ الاختبار -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="{{ $labelClass }}">تاريخ الاختبار *</label>
            <input type="date" name="test_date"
                value="{{ old('test_date', $isEdit ? $surahTest->test_date?->format('Y-m-d') : now()->format('Y-m-d')) }}"
                class="{{ $inputClass }}">
            @error('test_date')
            <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
            @enderror
        </div>
        <div></div>
    </div>

    <!-- ملاحظات -->
    <div>
        <label class="{{ $labelClass }}">ملاحظات</label>
        @if($isCreate || $isEdit)
        <textarea name="notes" rows="3" placeholder="أضف أي ملاحظات..."
            class="{{ $inputClass }} resize-none">{{ old('notes', $surahTest->notes ?? '') }}</textarea>
        @endif
        @error('notes')
        <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
        @enderror
    </div>
</div>

<!-- ═══════════════════════════════════════ -->
<!-- كروت نتائج الطلاب — موحّدة للإنشاء والتعديل -->
<!-- ═══════════════════════════════════════ -->
<div id="group-results-card">
    <div class="flex items-center justify-between mb-4 px-1">
        <h3 class="text-base font-bold text-[#1e2942]">
            نتائج الطلاب
            <span id="group-students-count" class="text-sm font-normal text-gray-400">({{ $results->count() }} طالب)</span>
        </h3>
    </div>

    <div id="group-results-list" class="space-y-4">
        @forelse($results as $index => $result)
        <div class="js-result-card bg-white rounded-2xl border border-gray-100 p-4 md:p-5 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
            @if($isEdit)
            <input type="hidden" name="results[{{ $index }}][id]" value="{{ $result->id }}">
            @endif
            <input type="hidden" name="results[{{ $index }}][student_id]" value="{{ $result->student_id }}">

            <!-- بيانات الطالب -->
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 shrink-0">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.4c-3.3 0-9.8 1.6-9.8 4.9v2.5h19.6v-2.5c0-3.3-6.5-4.9-9.8-4.9z" />
                    </svg>
                </div>
                <div class="text-right flex-1">
                    <div class="font-bold text-gray-900">{{ $result->student?->name ?? '—' }}</div>
                    <div class="text-xs text-gray-400">رقم الطالب: #{{ $result->student?->id ?? '—' }}</div>
                </div>
            </div>

            <!-- الحقول -->
            <div class="flex flex-wrap items-start gap-3 justify-end">
                <div class="w-23">
                    <p class="{{ $fieldLabelClass }}">الفتح</p>
                    <input type="number" min="0" name="results[{{ $index }}][prompt_errors]"
                        value="{{ old("results.{$index}.prompt_errors", $result->prompt_errors) }}"
                        class="js-prompt-errors {{ $fieldBoxClass }}">
                    @error("results.{$index}.prompt_errors")
                    <p class="text-[10px] text-red-600 mt-1 text-center">{{ $message }}</p>
                    @enderror
                </div>
                <div class="w-23">
                    <p class="{{ $fieldLabelClass }}">التشكيل</p>
                    <input type="number" min="0" name="results[{{ $index }}][tashkeel_errors]"
                        value="{{ old("results.{$index}.tashkeel_errors", $result->tashkeel_errors) }}"
                        class="js-tashkeel-errors {{ $fieldBoxClass }}">
                    @error("results.{$index}.tashkeel_errors")
                    <p class="text-[10px] text-red-600 mt-1 text-center">{{ $message }}</p>
                    @enderror
                </div>
                <div class="w-23">
                    <p class="{{ $fieldLabelClass }}">النسبة %</p>
                    <input type="number" min="0" max="100" name="results[{{ $index }}][percentage]"
                        value="{{ old("results.{$index}.percentage", $result->percentage) }}"
                        class="js-percentage {{ $fieldBoxClass }} {{ $percentageFieldClass }}"
                        @if(!$canEditPercentage) readonly @endif>
                    @error("results.{$index}.percentage")
                    <p class="text-[10px] text-red-600 mt-1 text-center">{{ $message }}</p>
                    @enderror
                </div>
                <div class="w-30">
                    <p class="{{ $fieldLabelClass }}">التقدير</p>
                    <select name="results[{{ $index }}][level]" class="js-level {{ $fieldBoxClass }}"
                        @if(!$canEditPercentage) style="{{ $levelFieldStyle }}" tabindex="-1" @endif>
                        <option value="">--</option>
                        @foreach($levels as $lvl)
                        <option value="{{ $lvl }}" {{ old("results.{$index}.level", $result->level) == $lvl ? 'selected' : '' }}>
                            {{ $lvl }}
                        </option>
                        @endforeach
                    </select>
                    @error("results.{$index}.level")
                    <p class="text-[10px] text-red-600 mt-1 text-center">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex-1 min-w-40">
                    <p class="{{ $fieldLabelClass }}">ملاحظات</p>
                    <input type="text" name="results[{{ $index }}][notes]"
                        value="{{ old("results.{$index}.notes", $result->notes) }}"
                        placeholder="ملاحظات..."
                        class="{{ $fieldBoxClass }} text-right! font-normal! px-3">
                    @error("results.{$index}.notes")
                    <p class="text-[10px] text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>
        @empty
        <div id="group-results-empty-row" class="bg-white rounded-2xl border border-gray-100 p-10 text-center text-gray-400 text-sm">
            {{ $isCreate ? 'اختر الحلقة لعرض الطلاب' : 'لا توجد نتائج مسجلة لهذا الاختبار.' }}
        </div>
        @endforelse
    </div>
</div>

@if($isCreate)
<!-- ═══════════════════════════════════════════════════════════ -->
<!-- قالب الكارت (مصدر التصميم الوحيد) — يُستنسخ عبر JS عند اختيار الحلقة -->
<!-- نفس البنية والكلاسات والـ Tailwind بالظبط زي الكارت اللي فوق -->
<!-- ═══════════════════════════════════════════════════════════ -->
<template id="student-card-template">
    <div class="js-result-card bg-white rounded-2xl border border-gray-100 p-4 md:p-5 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
        <input type="hidden" data-field="student_id" value="">

        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 shrink-0">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.4c-3.3 0-9.8 1.6-9.8 4.9v2.5h19.6v-2.5c0-3.3-6.5-4.9-9.8-4.9z" />
                </svg>
            </div>
            <div class="text-right flex-1">
                <div class="font-bold text-gray-900" data-field="name"></div>
                <div class="text-xs text-gray-400" data-field="id-label"></div>
            </div>
        </div>

        <div class="flex flex-wrap items-start gap-3 justify-end">
            <div class="w-23">
                <p class="{{ $fieldLabelClass }}">الفتح</p>
                <input type="number" min="0" value="0" data-field="prompt_errors" class="js-prompt-errors {{ $fieldBoxClass }}">
            </div>
            <div class="w-23">
                <p class="{{ $fieldLabelClass }}">التشكيل</p>
                <input type="number" min="0" value="0" data-field="tashkeel_errors" class="js-tashkeel-errors {{ $fieldBoxClass }}">
            </div>
            <div class="w-23">
                <p class="{{ $fieldLabelClass }}">النسبة %</p>
                <input type="number" min="0" max="100" value="100" data-field="percentage"
                    class="js-percentage {{ $fieldBoxClass }} {{ $percentageFieldClass }}"
                    @if(!$canEditPercentage) readonly @endif>
            </div>
            <div class="w-30">
                <p class="{{ $fieldLabelClass }}">التقدير</p>
                <select data-field="level" class="js-level {{ $fieldBoxClass }}"
                    @if(!$canEditPercentage) style="{{ $levelFieldStyle }}" tabindex="-1" @endif>
                    <option value="">--</option>
                    @foreach($levels as $lvl)
                    <option value="{{ $lvl }}">{{ $lvl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-40">
                <p class="{{ $fieldLabelClass }}">ملاحظات</p>
                <input type="text" placeholder="ملاحظات..." data-field="notes" class="{{ $fieldBoxClass }} text-right! font-normal! px-3">
            </div>
        </div>
    </div>
</template>
@endif
<script>
    // حساب النسبة والتقدير تلقائيًا بناءً على أخطاء الفتح والتشكيل
    document.addEventListener('input', function(e) {
        if (!e.target.matches('.js-prompt-errors, .js-tashkeel-errors')) return;

        const card = e.target.closest('.js-result-card');
        if (!card) return;

        calculateCardResult(card);
    });

    function calculateCardResult(card) {
        const promptInput = card.querySelector('.js-prompt-errors');
        const tashkeelInput = card.querySelector('.js-tashkeel-errors');
        const percentageInput = card.querySelector('.js-percentage');
        const levelSelect = card.querySelector('.js-level');

        const promptErrors = parseFloat(promptInput?.value) || 0;
        const tashkeelErrors = parseFloat(tashkeelInput?.value) || 0;

        // الفتح = خصم نقطة كاملة لكل خطأ، التشكيل = خصم نص نقطة لكل خطأ
        let percentage = 100 - (promptErrors * 1) - (tashkeelErrors * 0.5);
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
        if (percentage >= 95) return 'ممتاز';
        if (percentage >= 90) return 'جيد جداً';
        if (percentage >= 85) return 'جيد';
        if (percentage >= 80) return 'مقبول';
        return 'إعادة';
    }
</script>