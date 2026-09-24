@props([
'mode' => 'create',
'collectionRound' => null,
'circles' => collect(),
'creators' => collect(),
'breakdown' => collect(),
'previousRounds' => collect(),
'nextRoundNumber' => 1,
'selectedCircleId' => null,
'selectedMonth' => null,
'selectedSubscriptionIds' => [],
])

@php
$isCreate = $mode === 'create';
$isEdit = $mode === 'edit';

$round = $collectionRound;
$formattedMonth = $isEdit && $round ? $round->period_month->translatedFormat('F Y') : null;

$hasManagerNote = $isEdit && $round && !empty($round->manager_note);
$isNoteAddressed = $isEdit && $round && $round->manager_note_addressed;

$formAction = $isCreate
? route('collection-rounds.store')
: route('collection-rounds.update', $round->id);
$formMethod = 'POST';

$submitText = $isCreate ? 'تسجيل التحصيل' : 'حفظ التعديلات';

$pageTitle = $isCreate ? 'تحصيل جديد' : 'تعديل التحصيل';
$pageSubtitle = $isCreate
? 'تسجيل تحصيل اشتراكات للحلقة المحددة'
: 'التحصيل رقم ' . ($round->round_number ?? '—') . ' — ' . ($round->circle?->name ?? '—') . ' — ' . $formattedMonth;

$headerBtnRoute = route('collection-rounds.index');
$headerBtnText = $isCreate ? 'عودة' : 'العودة للتحصيلات';
$headerBtnIcon = $isCreate
? '
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />'
: '
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />';
@endphp

{{-- Header --}}
<div class="bg-[#0b3d2c] rounded-3xl p-8 text-white mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-black mb-2">{{ $pageTitle }}</h1>
            <p class="text-white/80">{{ $pageSubtitle }}</p>
        </div>
        <a href="{{ $headerBtnRoute }}"
            class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white px-5 py-2.5 rounded-xl transition-all duration-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {!! $headerBtnIcon !!}
            </svg>
            <span>{{ $headerBtnText }}</span>
        </a>
    </div>
</div>

{{-- ملاحظة المدير (edit mode only) --}}
@if($isEdit && $hasManagerNote)
<div class="mb-6 rounded-2xl border p-6 {{ $isNoteAddressed ? 'bg-gray-50 border-gray-200' : 'bg-red-50 border-red-200' }}">
    <div class="flex items-start gap-3">
        <svg class="w-6 h-6 shrink-0 {{ $isNoteAddressed ? 'text-gray-400' : 'text-red-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <div>
            <h3 class="font-bold text-sm mb-1 {{ $isNoteAddressed ? 'text-gray-600' : 'text-red-700' }}">
                @if($isNoteAddressed)
                ملاحظة سابقة من المدير (تمت معالجتها)
                @else
                ⚠️ ملاحظة من المدير تحتاج معالجتك
                @endif
            </h3>
            <p class="text-sm leading-relaxed {{ $isNoteAddressed ? 'text-gray-500' : 'text-red-600' }}">
                {{ $round->manager_note }}
            </p>
        </div>
    </div>
</div>
@endif

{{-- الفورم --}}
<form method="POST" action="{{ $formAction }}"
    x-data="collectionRoundForm({
        mode: '{{ $mode }}',
        circles: {{ Js::from($circles->map(fn($c) => ['value' => $c->id, 'label' => $c->name])) }},
        initialBreakdown: {{ Js::from($breakdown) }},
        initialPreviousRounds: {{ Js::from($previousRounds) }},
        initialNextRoundNumber: {{ $nextRoundNumber }},
        initialCircleId: {{ $selectedCircleId ?? 'null' }},
        initialMonth: '{{ $selectedMonth ?? now()->format('Y-m') }}',
        initialSubscriptionIds: {{ Js::from($selectedSubscriptionIds) }},
        initialSupervisorNote: {{ Js::from(old('supervisor_note', $isEdit ? $round->supervisor_note : '')) }},
        initialTotalAmount: {{ Js::from(old('total_amount', $isEdit ? $round->total_amount : 0)) }},
    })"
    @submit.prevent="confirmAndSubmit"
    class="space-y-6">
    @csrf
    @if($isEdit)
    @method('PUT')
    @endif

    <template x-if="submitError">
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4 flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <p class="text-sm text-red-700 font-medium" x-text="submitError"></p>
        </div>
    </template>

    {{-- ✅ منشئ التحصيل --}}
    @if(auth()->user()->hasAnyRole(['admin', 'general_manager']))
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-[#0b3d2c]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            منشئ التحصيل
        </h2>
        <div >
            <x-searchable-select
                name="created_by"
                placeholder="اختر المنشئ..."
                search-placeholder="بحث عن مشرف أو مدير..."
                :default-value="old('created_by', $isEdit ? $round->created_by : (auth()->user()->hasAnyRole(['supervisor', 'manager', 'general_manager']) ? auth()->id() : ''))"
                :options="$creators->map(fn($u) => ['value' => $u->id, 'label' => $u->name])" />
            <p class="text-xs text-gray-500 mt-2">يظهر فقط المشرفين والمدراء. اتركه فارغًا لاستخدام حسابك الحالي.</p>
        </div>
        <x-input-error :messages="$errors->get('created_by')" class="mt-2" />
    </div>
    @else
    <input type="hidden" name="created_by" value="{{ $isEdit ? $round->created_by : auth()->id() }}">
    @endif

    {{-- معلومات ثابتة (edit mode only) --}}
    @if($isEdit)
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-gray-50 rounded-xl">
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">الحلقة</label>
            <p class="text-sm font-bold text-gray-800">{{ $round->circle?->name ?? '—' }}</p>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">الشهر</label>
            <p class="text-sm font-bold text-gray-800">{{ $formattedMonth }}</p>
        </div>
    </div>
    @endif

    {{-- اختيار الحلقة والشهر (create mode only) --}}
    @if($isCreate)
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-[#0b3d2c]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            اختيار الحلقة والشهر
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Circle --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">الحلقة</label>
                <x-searchable-select
                    name="circle_id"
                    placeholder="اختر الحلقة..."
                    search-placeholder="ابحث عن حلقة..."
                    :default-value="$selectedCircleId"
                    :options="$circles->map(fn($c) => ['value' => $c->id, 'label' => $c->name])" />

                <template x-if="hasFieldError('circle_id')">
                    <ul class="mt-2 space-y-1">
                        <template x-for="err in getFieldError('circle_id')" :key="err">
                            <li class="text-sm text-red-600" x-text="err"></li>
                        </template>
                    </ul>
                </template>
                <x-input-error :messages="$errors->get('circle_id')" class="mt-2" />

                {{-- رسالة عدم وجود حلقات --}}
                <template x-if="circleError">
                    <p class="mt-2 text-sm text-red-600 font-medium" x-text="circleError"></p>
                </template>
            </div>

            {{-- Month --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">الشهر</label>
                <input type="month"
                    name="period_month"
                    x-model="selectedMonth"
                    @change="onMonthChange()"
                    max="{{ now()->format('Y-m') }}"
                    class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-[#0b3d2c] focus:border-[#0b3d2c] outline-none transition-all" />
                <template x-if="hasFieldError('period_month')">
                    <ul class="mt-2 space-y-1">
                        <template x-for="err in getFieldError('period_month')" :key="err">
                            <li class="text-sm text-red-600" x-text="err"></li>
                        </template>
                    </ul>
                </template>
                <x-input-error :messages="$errors->get('period_month')" class="mt-2" />
            </div>
        </div>
        @else
        {{-- Hidden fields for edit mode --}}
        <input type="hidden" name="circle_id" value="{{ $round->circle_id }}">
        <input type="hidden" name="period_month" value="{{ $round->period_month->format('Y-m') }}">
        @endif

        {{-- ملخص التحصيلات السابقة (create mode only) --}}
        @if($isCreate)
        <template x-if="previousRounds.length > 0">
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6">
                <div class="flex items-start gap-3 mb-4">
                    <svg class="w-6 h-6 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <h3 class="font-bold text-amber-800">هذه ليست أول التحصيل لهذه الحلقة هذا الشهر</h3>
                        <p class="text-amber-700 text-sm mt-1">
                            التحصيل رقم <span x-text="nextRoundNumber" class="font-bold"></span>
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <template x-for="round in previousRounds" :key="round.id">
                        <div class="bg-white rounded-xl p-4 border"
                            :class="round.manager_note ? 'border-red-200' : 'border-amber-100'">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-bold text-gray-900">التحصيل رقم <span x-text="round.round_number"></span></span>
                                <span :class="{
                                    'bg-emerald-100 text-emerald-700': round.status === 'confirmed',
                                    'bg-amber-100 text-amber-700': round.status === 'pending'
                                }"
                                    class="text-xs font-semibold px-2.5 py-1 rounded-full"
                                    x-text="round.status === 'confirmed' ? 'مؤكَّد' : 'معلّق'"></span>
                            </div>
                            <div class="text-sm text-gray-600">
                                <span x-text="round.total_amount.toLocaleString('ar-EG')"></span> جنيه
                                ·
                                <span x-text="round.students_count"></span> طالب
                            </div>
                            <div x-show="round.confirmed_at" class="text-xs text-gray-400 mt-1">
                                <span x-text="new Date(round.confirmed_at).toLocaleDateString('ar-EG')"></span>
                            </div>
                            <div x-show="round.manager_note" class="mt-2 text-xs text-red-600 bg-red-50 px-2 py-1 rounded">
                                ⚠️ <span x-text="round.manager_note"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
        @endif

        {{-- توزيع الاشتراكات (المحصّلين مع تفاصيل فردية) --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mt-6">
            <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
                <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-[#0b3d2c]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    توزيع الاشتراكات
                </h2>
                <div class="flex items-center gap-3 flex-wrap">
                    {{-- ملخص سريع --}}
                    <div class="text-sm text-gray-600 bg-gray-50 px-3 py-1.5 rounded-lg">
                        المحدد: <span x-text="selectedSubscriptionIds.length" class="font-semibold text-[#0b3d2c]"></span> اشتراك
                        · <span x-text="formatAmount(selectedTotal)" class="font-semibold text-[#0b3d2c]"></span>
                    </div>
                    <div class="flex gap-2">
                        <button type="button"
                            @click="selectAllSubscriptions()"
                            class="text-sm text-[#0b3d2c] hover:bg-[#0b3d2c]/5 px-3 py-1.5 rounded-lg transition-colors font-medium">
                            تحديد الكل
                        </button>
                        <button type="button"
                            @click="deselectAllSubscriptions()"
                            class="text-sm text-gray-500 hover:bg-gray-50 px-3 py-1.5 rounded-lg transition-colors font-medium">
                            إلغاء التحديد
                        </button>
                    </div>
                </div>
            </div>

            {{-- Loading State --}}
            <template x-if="isLoadingBreakdown">
                <div class="text-center py-12">
                    <svg class="animate-spin w-8 h-8 text-[#0b3d2c] mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-gray-400 text-sm mt-3">جاري تحميل البيانات...</p>
                </div>
            </template>

            {{-- Empty State --}}
            <template x-if="!isLoadingBreakdown && breakdown.length === 0">
                <div class="text-center py-12">
                    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="text-gray-500 font-medium">لا توجد مبالغ محصّلة مسجّلة لهذه الحلقة حتى الآن</p>
                    <p class="text-gray-400 text-sm mt-1">اختر حلقة وشهر مختلفين أو تحقق من وجود اشتراكات مدفوعة</p>
                </div>
            </template>

            {{-- Collectors List with Expandable Subscriptions --}}
            <template x-if="!isLoadingBreakdown && breakdown.length > 0">
                <div class="space-y-3">
                    <template x-for="item in breakdown" :key="item.id ?? 'null-' + item.name">
                        <div class="border rounded-xl transition-all duration-200 overflow-hidden"
                            :class="{
                            'border-emerald-300 bg-emerald-50/30': hasSelectedInCollector(item),
                            'border-red-200 bg-red-50/20': !hasSelectedInCollector(item) && item.id === null,
                            'border-gray-200': !hasSelectedInCollector(item) && item.id !== null
                        }">

                            {{-- Collector Header (Clickable to Expand/Collapse) --}}
                            <div @click="toggleCollectorExpand(item.id)"
                                class="flex items-center gap-4 p-4 cursor-pointer hover:bg-gray-50/50 transition-colors"
                                role="button"
                                :aria-expanded="isCollectorExpanded(item.id)"
                                tabindex="0"
                                @keydown.enter.prevent="toggleCollectorExpand(item.id)"
                                @keydown.space.prevent="toggleCollectorExpand(item.id)">

                                {{-- Expand/Collapse Icon --}}
                                <div class="shrink-0">
                                    <svg class="w-5 h-5 text-gray-400 transition-transform duration-200"
                                        :class="{ 'rotate-90': isCollectorExpanded(item.id) }"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </div>

                                {{-- Collector Info --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-semibold text-gray-900" x-text="item.name"></span>
                                        <template x-if="item.id === null">
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-red-600 bg-red-100 px-2 py-0.5 rounded-full">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                </svg>
                                                محصّل غير محدد — يحتاج مراجعة
                                            </span>
                                        </template>
                                        <template x-if="item.has_new_items">
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-amber-600 bg-amber-100 px-2 py-0.5 rounded-full">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                </svg>
                                                جديد
                                            </span>
                                        </template>
                                    </div>

                                    {{-- Progress Bar + Stats --}}
                                    <div class="mt-2">
                                        <div class="flex items-center justify-between text-xs mb-1">
                                            <span class="text-gray-500">
                                                المحدد: <span class="font-medium text-[#0b3d2c]" x-text="countSelectedInCollector(item)"></span>
                                                من <span x-text="item.subscriptions ? item.subscriptions.length : 0"></span> اشتراك
                                            </span>
                                            <span class="font-medium text-[#0b3d2c]" x-text="formatAmount(selectedAmountInCollector(item)) + ' / ' + formatAmount(item.amount)"></span>
                                        </div>
                                        {{-- Progress bar --}}
                                        <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                            <div class="bg-[#0b3d2c] h-1.5 rounded-full transition-all duration-300"
                                                :style="'width: ' + (item.subscriptions && item.subscriptions.length > 0 ? (countSelectedInCollector(item) / item.subscriptions.length * 100) : 0) + '%'"
                                                :class="countSelectedInCollector(item) === (item.subscriptions ? item.subscriptions.length : 0) ? 'bg-emerald-500' : 'bg-[#0b3d2c]'"></div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Toggle All Button for This Collector --}}
                                <button type="button"
                                    @click.stop="toggleAllForCollector(item.id)"
                                    class="shrink-0 text-xs px-3 py-1.5 rounded-lg transition-colors font-medium"
                                    :class="allSubscriptionsSelected(item) 
                                    ? 'bg-red-100 text-red-700 hover:bg-red-200' 
                                    : 'bg-[#0b3d2c]/10 text-[#0b3d2c] hover:bg-[#0b3d2c]/20'"
                                    x-text="allSubscriptionsSelected(item) ? 'إلغاء الكل' : 'تحديد الكل'"
                                    role="checkbox"
                                    :aria-checked="allSubscriptionsSelected(item)">
                                </button>

                                {{-- Total Amount --}}
                                <div class="text-left flex-shrink-0 min-w-[100px] hidden sm:block">
                                    <div class="text-lg font-bold text-gray-800" x-text="formatAmount(item.amount)"></div>
                                    <div class="text-xs text-gray-400">إجمالي المحصّل</div>
                                </div>
                            </div>

                            {{-- Expanded Subscriptions List --}}
                            <div x-show="isCollectorExpanded(item.id)"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 max-h-0"
                                x-transition:enter-end="opacity-100 max-h-[2000px]"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 max-h-[2000px]"
                                x-transition:leave-end="opacity-0 max-h-0"
                                class="border-t border-gray-100 overflow-hidden">

                                <div class="p-4 space-y-3">
                                    {{-- Search within collector --}}
                                    <div class="relative" x-show="item.subscriptions && item.subscriptions.length > 5">
                                        <svg class="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        <input
                                            type="text"
                                            x-model="searchTerms[item.id ?? 'null']"
                                            @click.stop
                                            placeholder="ابحث عن طالب..."
                                            class="w-full pr-10 pl-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#0b3d2c] focus:border-[#0b3d2c] outline-none transition-all">
                                    </div>

                                    <template x-if="!item.subscriptions || item.subscriptions.length === 0">
                                        <p class="text-sm text-gray-400 text-center py-4">لا توجد اشتراكات تفصيلية</p>
                                    </template>

                                    <template x-for="sub in filteredSubscriptions(item)" :key="sub.id">
                                        <div @click="toggleSubscription(sub.id)"
                                            class="flex items-center gap-4 p-3 rounded-lg cursor-pointer transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-[#0b3d2c] focus:ring-offset-1"
                                            :class="{
                                            'bg-emerald-50 border-2 border-emerald-300': isSelected(sub.id) && sub.is_original !== false,
                                            'bg-amber-50 border-2 border-amber-300': sub.is_original === false && !isSelected(sub.id),
                                            'bg-amber-100 border-2 border-amber-400': sub.is_original === false && isSelected(sub.id),
                                            'bg-gray-50/50 border-2 border-transparent hover:bg-gray-100': !isSelected(sub.id) && sub.is_original !== false
                                        }"
                                            role="checkbox"
                                            :aria-checked="isSelected(sub.id)"
                                            tabindex="0"
                                            @keydown.space.prevent="toggleSubscription(sub.id)"
                                            @keydown.enter.prevent="toggleSubscription(sub.id)">

                                            {{-- Checkbox --}}
                                            <div class="shrink-0">
                                                <div :class="isSelected(sub.id) ? 'bg-[#0b3d2c] border-[#0b3d2c]' : 'border-gray-300'"
                                                    class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors">
                                                    <svg x-show="isSelected(sub.id)" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </div>
                                            </div>

                                            {{-- Subscription Info --}}
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-medium text-gray-900" x-text="sub.student_name"></span>
                                                    <template x-if="sub.is_original === false">
                                                        <span class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-semibold">جديد</span>
                                                    </template>
                                                    <template x-if="sub.is_original === true">
                                                        <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full">أصلي</span>
                                                    </template>
                                                </div>
                                                <div class="text-xs text-gray-500 mt-0.5">
                                                    اشتراك #<span x-text="sub.id"></span>
                                                </div>
                                            </div>

                                            {{-- Amount --}}
                                            <div class="text-left flex-shrink-0">
                                                <span class="font-semibold text-gray-800" x-text="formatAmount(sub.amount)"></span>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- No search results --}}
                                    <template x-if="filteredSubscriptions(item).length === 0 && item.subscriptions && item.subscriptions.length > 0">
                                        <p class="text-sm text-gray-400 text-center py-4">لا توجد نتائج مطابقة للبحث</p>
                                    </template>
                                </div>

                                {{-- تنبيه الزيادة على مستوى الاشتراك (edit mode) --}}
                                <template x-if="item.has_new_items">
                                    <div class="mx-4 mb-4 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 flex items-start gap-2">
                                        <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <p class="text-xs text-amber-700 font-bold leading-relaxed">
                                            ⚠️ يشمل هذا المحصّل زيادة جديدة قدرها
                                            <span class="text-red-600 bg-red-100 px-1.5 py-0.5 rounded font-black" x-text="formatAmount(item.new_amount)"></span>
                                            (<span class="text-red-600 font-black" x-text="item.new_subscription_ids.length"></span> اشتراك)
                                            سُجّلت بعد إنشاء التحصيل ولم تُحصَّل بعد فعليًا. تأكد من استلامها فعلاً قبل الحفظ.
                                        </p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Hidden inputs for selected subscription IDs --}}
            <template x-for="id in selectedSubscriptionIds" :key="id">
                <input type="hidden" name="selected_subscription_ids[]" :value="id">
            </template>

            <template x-if="hasFieldError('selected_subscription_ids')">
                <ul class="mt-4 space-y-1">
                    <template x-for="err in getFieldError('selected_subscription_ids')" :key="err">
                        <li class="text-sm text-red-600" x-text="err"></li>
                    </template>
                </ul>
            </template>
            <x-input-error :messages="$errors->get('selected_subscription_ids')" class="mt-4" />
            <x-input-error :messages="$errors->get('selected_subscription_ids.*')" class="mt-2" />
        </div>

        {{-- إجمالي المبلغ (يدوي — create + edit) --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mt-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-[#0b3d2c]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                إجمالي المبلغ
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">إجمالي المبلغ (جنيه)</label>
                    <input type="number"
                        name="total_amount"
                        x-model="totalAmountInput"
                        @input="markManualEdit()"
                        min="0"
                        step="0.01"
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-[#0b3d2c] focus:border-[#0b3d2c] outline-none transition-all"
                        placeholder="0.00" />
                    <p class="text-xs text-gray-500 mt-1.5">
                        @if($isCreate)
                        تُملأ تلقائيًا حسب الاشتراكات المختارة، ويمكنك تعديلها يدويًا.
                        @else
                        آخر قيمة مسجَّلة لهذا التحصيل — يمكنك تعديلها إذا استلمت مبلغًا مختلفًا.
                        @endif
                    </p>
                    <template x-if="hasFieldError('total_amount')">
                        <ul class="mt-2 space-y-1">
                            <template x-for="err in getFieldError('total_amount')" :key="err">
                                <li class="text-sm text-red-600" x-text="err"></li>
                            </template>
                        </ul>
                    </template>
                    <x-input-error :messages="$errors->get('total_amount')" class="mt-2" />
                </div>

                <div class="flex flex-col justify-center">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-gray-600">المستحق الآن</span>
                        <span class="font-bold text-gray-900" x-text="formatAmount(selectedTotal)"></span>
                    </div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-gray-600">القيمة المُدخلة:</span>
                        <span class="font-bold text-[#0b3d2c]" x-text="formatAmount(parseFloat(totalAmountInput) || 0)"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">الفرق:</span>
                        <span class="font-bold"
                            :class="difference === 0 ? 'text-emerald-600' : (difference > 0 ? 'text-amber-600' : 'text-red-600')"
                            x-text="(difference >= 0 ? '+' : '') + formatAmount(difference)"></span>
                    </div>
                    <!-- ✅ تحسين تنبيه الفرق -->
                    <div x-show="difference !== 0"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 transform -translate-y-2"
                        x-transition:enter-end="opacity-100 transform translate-y-0"
                        class="mt-4 rounded-xl border-2 p-4 flex items-start gap-3"
                        :class="difference > 0 
                        ? 'bg-amber-50 border-amber-300' 
                        : 'bg-red-50 border-red-300'">

                        <div class="shrink-0 mt-0.5">
                            <svg class="w-6 h-6" :class="difference > 0 ? 'text-amber-500' : 'text-red-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>

                        <div class="flex-1">
                            <h4 class="font-bold text-sm mb-1"
                                :class="difference > 0 ? 'text-amber-800' : 'text-red-800'"
                                x-text="difference > 0 ? '⚠️ تنبيه: مبلغ ناقص' : '⚠️ تنبيه: مبلغ زائد'"></h4>
                            <p class="text-sm leading-relaxed" :class="difference > 0 ? 'text-amber-700' : 'text-red-700'">
                                <template x-if="difference > 0">
                                    <span>
                                        المبلغ المُدخل (<span class="font-bold" x-text="formatAmount(parseFloat(totalAmountInput) || 0)"></span>)
                                        أقل من المستحق الفعلي بمقدار
                                        <span class="font-bold bg-amber-100 px-1.5 py-0.5 rounded" x-text="formatAmount(Math.abs(difference))"></span>.
                                        <br>هذا يعني أنك لم تُحصّل بعض الاشتراكات المحددة.
                                    </span>
                                </template>
                                <template x-if="difference < 0">
                                    <span>
                                        المبلغ المُدخل (<span class="font-bold" x-text="formatAmount(parseFloat(totalAmountInput) || 0)"></span>)
                                        أكبر من المستحق الفعلي بمقدار
                                        <span class="font-bold bg-red-100 px-1.5 py-0.5 rounded" x-text="formatAmount(Math.abs(difference))"></span>.
                                        <br>تأكد من صحة المبلغ المُدخل أو راجع الاشتراكات المحددة.
                                    </span>
                                </template>
                            </p>
                            <div class="mt-2 flex items-center gap-2">
                                <button type="button"
                                    @click="totalAmountInput = selectedTotal; totalAmountManuallyEdited = false"
                                    class="text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors"
                                    :class="difference > 0 
                                    ? 'bg-amber-200 text-amber-800 hover:bg-amber-300' 
                                    : 'bg-red-200 text-red-800 hover:bg-red-300'">
                                    ✓ تصحيح تلقائي للمبلغ
                                </button>
                                <span class="text-xs opacity-60" :class="difference > 0 ? 'text-amber-600' : 'text-red-600'">
                                    أو عدّل المبلغ يدويًا
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ملاحظة المشرف --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mt-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">ملاحظة المشرف</label>
            <textarea name="supervisor_note"
                x-model="supervisorNote"
                rows="3"
                maxlength="1000"
                class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-[#0b3d2c] focus:border-[#0b3d2c] outline-none transition-all resize-none"
                placeholder="أي ملاحظات إضافية حول التحصيل..."></textarea>
            <template x-if="hasFieldError('supervisor_note')">
                <ul class="mt-2 space-y-1">
                    <template x-for="err in getFieldError('supervisor_note')" :key="err">
                        <li class="text-sm text-red-600" x-text="err"></li>
                    </template>
                </ul>
            </template>
            <x-input-error :messages="$errors->get('supervisor_note')" class="mt-2" />
        </div>

        {{-- ملخص التحديد (edit mode) --}}
        @if($isEdit)
        <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-100">
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600">الاشتراكات المُختارة:</span>
                <span class="font-bold text-[#0b3d2c]" x-text="selectedSubscriptionIds.length"></span>
            </div>
            <div class="flex items-center justify-between text-sm mt-2">
                <span class="text-gray-600">إجمالي المبلغ:</span>
                <span class="font-bold text-[#0b3d2c]" x-text="formatAmount(selectedTotal)"></span>
            </div>
        </div>
        @endif

        {{-- أزرار --}}
        <div class="flex mt-6 {{ $isCreate ? 'justify-end' : 'flex-col sm:flex-row gap-3 pt-4 border-t border-gray-100' }}">
            <button type="submit"
                @if($isCreate)
                :disabled="!selectedCircle || selectedSubscriptionIds.length === 0 || isSubmitting"
                :class="(!selectedCircle || selectedSubscriptionIds.length === 0 || isSubmitting) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-[#094a36] hover:shadow-lg hover:-translate-y-0.5'"
                @else
                :disabled="selectedSubscriptionIds.length === 0 || isSubmitting"
                :class="(selectedSubscriptionIds.length === 0 || isSubmitting) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-[#094a36] hover:shadow-lg hover:-translate-y-0.5'"
                @endif
                class="bg-[#0b3d2c] text-white px-8 py-3.5 rounded-xl font-bold text-base transition-all duration-200 flex items-center gap-2 {{ $isEdit ? 'flex-1 justify-center' : '' }}">
                <svg x-show="isSubmitting" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="isSubmitting ? 'جاري الحفظ...' : '{{ $submitText }}'"></span>
            </button>

            @if($isEdit)
            <a href="{{ route('collection-rounds.index') }}"
                class="flex-1 bg-gray-100 text-gray-600 px-6 py-3 rounded-xl font-bold hover:bg-gray-200 transition flex items-center justify-center gap-2 text-center">
                إلغاء
            </a>
            @endif
        </div>
</form>

<script>
    function collectionRoundForm(config) {
        return {
            mode: config.mode,
            circles: config.circles,
            selectedCircle: config.initialCircleId,
            selectedMonth: config.initialMonth,
            breakdown: config.initialBreakdown,
            previousRounds: config.initialPreviousRounds,
            nextRoundNumber: config.initialNextRoundNumber,
            selectedSubscriptionIds: config.initialSubscriptionIds,
            selectedSubscriptionSet: new Set(config.initialSubscriptionIds),
            totalAmountInput: config.initialTotalAmount,
            totalAmountManuallyEdited: false,
            supervisorNote: config.initialSupervisorNote,
            isSubmitting: false,
            isLoadingBreakdown: false,
            isLoadingCircles: false,
            submitError: '',
            circleError: '',
            validationErrors: {},
            expandedCollectors: {},
            searchTerms: {},
            currentAbortController: null,

            init() {
                // ✅ استمع لأحداث searchable-select من الـ window
                window.addEventListener('searchable-change', (e) => {
                    if (e.detail.name === 'created_by' && this.mode === 'create') {
                        const userId = e.detail.value ? parseInt(e.detail.value) : null;
                        this.fetchAvailableCirclesForUser(userId);
                    }
                    if (e.detail.name === 'circle_id') {
                        this.selectedCircle = e.detail.value ? parseInt(e.detail.value) : null;
                        this.fetchBreakdown();
                    }
                });

                // ✅ تحسين 1: توسيع تلقائي في وضع الإنشاء
                if (this.mode === 'create') {
                    for (const item of this.breakdown) {
                        this.expandedCollectors[item.id ?? 'null'] = true;
                    }
                    // تحديد الكل افتراضيًا
                    if (this.selectedSubscriptionIds.length === 0) {
                        this.selectAllSubscriptions();
                    }
                }
                // ✅ توسيع تلقائي لـ has_new_items في وضع التعديل
                else if (this.mode === 'edit') {
                    for (const item of this.breakdown) {
                        if (item.has_new_items) {
                            this.expandedCollectors[item.id ?? 'null'] = true;
                        }
                    }
                }

                // تعبئة تلقائية للمبلغ الإجمالي
                if (this.mode === 'create') {
                    this.$watch('selectedSubscriptionIds', () => {
                        if (!this.totalAmountManuallyEdited) {
                            this.totalAmountInput = this.selectedTotal;
                        }
                    });
                }
            },

            markManualEdit() {
                this.totalAmountManuallyEdited = true;
            },

            // ✅ تحسين 2: استخدام Set للأداء
            get selectedTotal() {
                let total = 0;
                for (const item of this.breakdown) {
                    if (item.subscriptions) {
                        for (const sub of item.subscriptions) {
                            if (this.selectedSubscriptionSet.has(sub.id)) {
                                total += parseFloat(sub.amount);
                            }
                        }
                    }
                }
                return total;
            },

            get difference() {
                return this.selectedTotal - (parseFloat(this.totalAmountInput) || 0);
            },

            isSelected(subscriptionId) {
                return this.selectedSubscriptionSet.has(subscriptionId);
            },

            toggleSubscription(subscriptionId) {
                if (this.selectedSubscriptionSet.has(subscriptionId)) {
                    this.selectedSubscriptionSet.delete(subscriptionId);
                } else {
                    this.selectedSubscriptionSet.add(subscriptionId);
                }
                this.selectedSubscriptionIds = Array.from(this.selectedSubscriptionSet);
            },

            toggleCollectorExpand(collectorId) {
                const key = collectorId ?? 'null';
                this.expandedCollectors[key] = !this.expandedCollectors[key];
            },

            isCollectorExpanded(collectorId) {
                const key = collectorId ?? 'null';
                return !!this.expandedCollectors[key];
            },

            toggleAllForCollector(collectorId) {
                const collector = this.breakdown.find(item => item.id === collectorId);
                if (!collector || !collector.subscriptions) return;

                const subscriptionIds = collector.subscriptions.map(sub => sub.id);
                const allSelected = subscriptionIds.every(id => this.isSelected(id));

                if (allSelected) {
                    subscriptionIds.forEach(id => this.selectedSubscriptionSet.delete(id));
                } else {
                    subscriptionIds.forEach(id => this.selectedSubscriptionSet.add(id));
                }
                this.selectedSubscriptionIds = Array.from(this.selectedSubscriptionSet);
            },

            allSubscriptionsSelected(collector) {
                if (!collector.subscriptions || collector.subscriptions.length === 0) return false;
                return collector.subscriptions.every(sub => this.isSelected(sub.id));
            },

            hasSelectedInCollector(collector) {
                if (!collector.subscriptions) return false;
                return collector.subscriptions.some(sub => this.isSelected(sub.id));
            },

            countSelectedInCollector(collector) {
                if (!collector.subscriptions) return 0;
                return collector.subscriptions.filter(sub => this.isSelected(sub.id)).length;
            },

            selectedAmountInCollector(collector) {
                if (!collector.subscriptions) return 0;
                return collector.subscriptions
                    .filter(sub => this.isSelected(sub.id))
                    .reduce((sum, sub) => sum + parseFloat(sub.amount), 0);
            },

            selectAllSubscriptions() {
                for (const item of this.breakdown) {
                    if (item.subscriptions) {
                        item.subscriptions.forEach(sub => this.selectedSubscriptionSet.add(sub.id));
                    }
                }
                this.selectedSubscriptionIds = Array.from(this.selectedSubscriptionSet);
            },

            deselectAllSubscriptions() {
                this.selectedSubscriptionSet.clear();
                this.selectedSubscriptionIds = [];
            },

            // ✅ تحسين 5: فلترة الاشتراكات حسب البحث
            filteredSubscriptions(collector) {
                if (!collector.subscriptions) return [];
                const searchTerm = (this.searchTerms[collector.id ?? 'null'] || '').trim().toLowerCase();
                if (!searchTerm) return collector.subscriptions;
                return collector.subscriptions.filter(sub =>
                    (sub.student_name || '').toLowerCase().includes(searchTerm) ||
                    String(sub.id).includes(searchTerm)
                );
            },

            async onMonthChange() {
                if (this.mode === 'create') {
                    await this.fetchAvailableCircles();
                }
                this.fetchBreakdown();
            },

            async fetchAvailableCircles() {
                try {
                    this.isLoadingCircles = true;
                    this.circleError = '';
                    const response = await fetch(
                        `{{ route('collection-rounds.available-circles') }}?period_month=${this.selectedMonth}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        }
                    );

                    if (!response.ok) throw new Error('Network response was not ok');

                    const data = await response.json();
                    // ✅ البيانات القادمة من السيرفر بالفعل بالشكل {value, label} — لا داعي لإعادة تهيئتها من c.id/c.name
                    const formattedCircles = (data.circles || []).map(c => ({
                        value: c.value,
                        label: c.label
                    }));
                    this.circles = formattedCircles;

                    window.dispatchEvent(new CustomEvent('update-options', {
                        detail: {
                            name: 'circle_id',
                            options: formattedCircles
                        }
                    }));

                    const stillAvailable = data.circles.some(c => c.value == this.selectedCircle);
                    if (this.selectedCircle && !stillAvailable) {
                        this.selectedCircle = null;
                        this.breakdown = [];
                        this.selectedSubscriptionSet.clear();
                        this.selectedSubscriptionIds = [];
                    }
                } catch (error) {
                    console.error('Error fetching available circles:', error);
                } finally {
                    this.isLoadingCircles = false;
                }
            },
            async fetchAvailableCirclesForUser(userId) {
                // لا شيء في وضع التعديل
                if (this.mode !== 'create') return;

                // إذا لم يُختَر مستخدم، نستخدم السلوك الافتراضي (الحلقات الحالية)
                if (!userId) {
                    this.circleError = '';
                    await this.fetchAvailableCircles();
                    return;
                }
                this.isLoadingCircles = true;
                this.circleError = '';
                try {
                    const response = await fetch(
                        `{{ route('collection-rounds.available-circles-for-user') }}?user_id=${userId}&period_month=${this.selectedMonth}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        }
                    );

                    if (!response.ok) throw new Error('Network response was not ok');

                    const data = await response.json();

                    // تحديث dropdown الحلقات
                    // ✅ البيانات القادمة من السيرفر بالفعل بالشكل {value, label} — لا داعي لإعادة تهيئتها من c.id/c.name
                    const formattedCircles = (data.circles || []).map(c => ({
                        value: c.value,
                        label: c.label
                    }));
                    this.circles = formattedCircles;
                    window.dispatchEvent(new CustomEvent('update-options', {
                        detail: {
                            name: 'circle_id',
                            options: formattedCircles
                        }
                    }));

                    // إذا الحلقة المختارة غير متاحة للمستخدم الجديد، أو لا توجد حلقات على الإطلاق
                    const stillAvailable = data.circles.some(c => c.value == this.selectedCircle);
                    if ((this.selectedCircle && !stillAvailable) || data.circles.length === 0) {
                        this.selectedCircle = null;
                        this.breakdown = [];
                        this.selectedSubscriptionSet.clear();
                        this.selectedSubscriptionIds = [];
                        window.dispatchEvent(new CustomEvent('clear-selection', {
                            detail: {
                                name: 'circle_id'
                            }
                        }));
                    }

                    // رسالة إذا لم يكن للمستخدم أي حلقة
                    if (data.circles.length === 0) {
                        this.circleError = 'هذا المستخدم غير مرتبط بأي حلقة حاليًا';
                    } else {
                        this.circleError = '';
                    }
                } catch (error) {
                    console.error('Error fetching circles for user:', error);
                    this.circleError = 'حدث خطأ أثناء جلب الحلقات';
                } finally {
                    this.isLoadingCircles = false;
                }
            },

            async fetchBreakdown() {
                if (!this.selectedCircle || !this.selectedMonth) return;

                // ✅ تحسين 7: إلغاء الطلب السابق
                if (this.currentAbortController) {
                    this.currentAbortController.abort();
                }
                this.currentAbortController = new AbortController();

                this.isLoadingBreakdown = true;

                try {
                    const response = await fetch(
                        `{{ route('collection-rounds.breakdown') }}?circle_id=${this.selectedCircle}&period_month=${this.selectedMonth}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            signal: this.currentAbortController.signal,
                        }
                    );

                    if (!response.ok) throw new Error('Network response was not ok');

                    const data = await response.json();
                    this.breakdown = data.breakdown;
                    this.previousRounds = data.previous_rounds;
                    this.nextRoundNumber = data.next_round_number;
                    this.selectedSubscriptionSet.clear();
                    this.selectedSubscriptionIds = [];

                    // توسيع الكل وتحديد الكل في وضع الإنشاء
                    this.expandedCollectors = {};
                    for (const item of this.breakdown) {
                        this.expandedCollectors[item.id ?? 'null'] = true;
                    }
                    this.selectAllSubscriptions();
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        console.error('Error fetching breakdown:', error);
                    }
                } finally {
                    this.isLoadingBreakdown = false;
                    this.currentAbortController = null;
                }
            },

            confirmAndSubmit() {
                const total = parseFloat(this.totalAmountInput) || 0;
                window.confirmRoundAmount(total, () => {
                    this.submitForm();
                });
            },

            async submitForm() {
                if (this.isSubmitting) return;

                this.isSubmitting = true;
                this.submitError = '';
                this.validationErrors = {};

                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 20000);

                try {
                    const formData = new FormData(this.$el);

                    const response = await fetch(this.$el.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: controller.signal,
                    });

                    clearTimeout(timeoutId);

                    const data = await response.json().catch(() => ({}));

                    if (response.ok) {
                        if (data.redirect_url) {
                            window.location.href = data.redirect_url;
                        } else {
                            window.location.href = '{{ route('collection-rounds.index') }}';
                        }
                        return;
                    }

                    if (response.status === 422) {
                        if (data.errors) {
                            this.validationErrors = data.errors;
                        } else if (data.message) {
                            this.submitError = data.message;
                        } else {
                            this.submitError = 'يرجى التحقق من البيانات المدخلة.';
                        }
                    } else if (response.status === 403) {
                        this.submitError = data.message || 'ليس لديك صلاحية لإجراء هذه العملية.';
                    } else {
                        this.submitError = data.message || 'حدث خطأ أثناء الحفظ. حاول مرة أخرى.';
                    }
                } catch (error) {
                    clearTimeout(timeoutId);

                    if (error.name === 'AbortError') {
                        this.submitError = 'استغرقت العملية وقتًا طويلاً. حاول مرة أخرى.';
                    } else {
                        this.submitError = 'حدث خطأ أثناء الحفظ. حاول مرة أخرى.';
                    }
                } finally {
                    this.isSubmitting = false;
                }
            },

            getFieldError(field) {
                return this.validationErrors[field] || [];
            },

            hasFieldError(field) {
                return field in this.validationErrors && this.validationErrors[field].length > 0;
            },

            formatAmount(amount) {
                return new Intl.NumberFormat('ar-EG', {
                    style: 'decimal',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(amount) + ' جنيه';
            }
        };
    }
</script>