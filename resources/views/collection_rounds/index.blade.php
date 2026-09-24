@php
$formattedMonth = isset($filters['period_month']) && $filters['period_month']
? \Carbon\Carbon::createFromFormat('Y-m', $filters['period_month'])->translatedFormat('F Y')
: \Carbon\Carbon::now()->translatedFormat('F Y');
@endphp

<x-layouts.markaz-layout>
    {{-- Header --}}
    <div class="bg-[#0b3d2c] rounded-3xl p-8 text-white mb-6 relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6">
        <div class="text-right w-full md:w-auto z-10">
            <h1 class="text-3xl font-black mb-2">تحصيل الاشتراكات</h1>
            <p class="text-emerald-100/80 text-sm font-medium">متابعة الاشتراكات والمراجعة</p>
        </div>
        @can('create collection rounds')
        <a href="{{ route('collection-rounds.create') }}"
            class="w-full md:w-auto px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-2xl flex items-center justify-center gap-2 transition-all shadow-lg hover:shadow-emerald-500/20 active:scale-95">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            تحصيل جديد
        </a>
        @endcan
    </div>

    {{-- فلاتر متقدمة --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6"
        x-data="collectionRoundFilters({
            initialCenter: '{{ $selectedCenterId ?? '' }}',
            initialBranch: '{{ $selectedBranchId ?? '' }}',
            initialCircle: '{{ $selectedCircleId ?? '' }}',
            initialCreator: '{{ $selectedCreatorId ?? '' }}',
            allCenters: {{ Js::from($centers) }},
            allBranches: {{ Js::from($branches) }},
            allCircles: {{ Js::from($allCircles) }},
            allCreators: {{ Js::from($allCreators) }},
        })"
        x-init="init()">

        <form method="GET" action="{{ route('collection-rounds.index') }}" class="space-y-4" id="filterForm">

            {{-- الصف الأول: البحث والأزرار --}}
            <div class="flex flex-col md:flex-row gap-4 items-end pb-4 border-b border-gray-100">
                <div class="w-full md:flex-1">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">بحث (حلقة / مشرف / مدير)</label>
                    <div class="relative">
                        <input type="text" name="search" value="{{ $search ?? '' }}"
                            placeholder="ابحث باسم الحلقة أو المشرف أو المدير..."
                            class="w-full rounded-xl border-gray-200 pr-10 focus:border-[#0b3d2c] focus:ring-[#0b3d2c] text-sm h-11"
                            oninput="debounceSearch(this)">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 w-full md:w-auto justify-end">
                    @if($hasActiveFilters ?? false)
                    <a href="{{ route('collection-rounds.index') }}"
                        class="w-1/2 md:w-auto justify-center bg-gray-100 text-gray-600 px-5 h-11 rounded-xl font-bold hover:bg-gray-200 transition flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 4H15" />
                        </svg>
                        إعادة تعيين
                    </a>
                    @endif
                    <button type="submit"
                        class="w-1/2 md:w-auto justify-center bg-[#0b3d2c] text-white px-6 h-11 rounded-xl font-bold hover:bg-[#0a3324] shadow-sm transition flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z" />
                        </svg>
                        تطبيق التصفية
                    </button>
                </div>
            </div>

            {{-- الصف الثاني: الفلاتر --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">

                {{-- فلتر المركز --}}
                @if(auth()->user()->hasAnyRole(['admin', 'general_manager']))
                @if(isset($centers) && $centers->count() > 0)
                <div class="w-full">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">المركز</label>
                    <x-searchable-select
                        name="center_id"
                        placeholder="جميع المراكز"
                        searchPlaceholder="بحث في المراكز..."
                        defaultOption="جميع المراكز"
                        defaultValue="{{ $selectedCenterId ?? '' }}"
                        :options="$centers->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values()"
                        x-model="selectedCenter" />
                </div>
                @endif
                @endif

                {{-- فلتر الفرع --}}
                @if(isset($branches) && $branches->count() > 0)
                <div class="w-full">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">الفرع</label>
                    <x-searchable-select
                        name="branch_id"
                        placeholder="جميع الفروع"
                        searchPlaceholder="بحث في الفروع..."
                        defaultOption="جميع الفروع"
                        defaultValue="{{ $selectedBranchId ?? '' }}"
                        :options="$branches->map(fn($b) => ['value' => $b->id, 'label' => $b->name])->values()"
                        x-model="selectedBranch"
                        x-effect="updateOptions(branchOptions)" />
                </div>
                @endif

                {{-- فلتر الحلقة --}}
                <div class="w-full">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">الحلقة</label>
                    <x-searchable-select
                        name="circle_id"
                        placeholder="جميع الحلقات"
                        searchPlaceholder="بحث في الحلقات..."
                        defaultOption="جميع الحلقات"
                        defaultValue="{{ $selectedCircleId ?? '' }}"
                        :options="$allCircles->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values()"
                        x-model="selectedCircle"
                        x-effect="updateOptions(circleOptions)" />
                </div>

                {{-- فلتر الشهر --}}
                <div class="w-full">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">الشهر</label>
                    <input type="month" name="period_month" value="{{ $filters['period_month'] ?? '' }}"
                        class="w-full rounded-xl border-gray-200 focus:border-[#0b3d2c] focus:ring-[#0b3d2c] text-sm h-11">
                </div>

                {{-- فلتر الحالة --}}
                <div class="w-full">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">الحالة</label>
                    <select name="status"
                        class="w-full rounded-xl border-gray-200 focus:border-[#0b3d2c] focus:ring-[#0b3d2c] text-sm h-11">
                        <option value="">الكل</option>
                        <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>معلّق</option>
                        <option value="confirmed" {{ ($filters['status'] ?? '') === 'confirmed' ? 'selected' : '' }}>مؤكَّد</option>
                    </select>
                </div>

                {{-- فلتر منشئ التحصيل (المشرف) --}}
                <div class="w-full">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">منشئ التحصيل</label>
                    <x-searchable-select
                        name="created_by"
                        placeholder="جميع المشرفين"
                        searchPlaceholder="بحث في المشرفين..."
                        defaultOption="جميع المشرفين"
                        defaultValue="{{ $selectedCreatorId ?? '' }}"
                        :options="$allCreators->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values()"
                        x-model="selectedCreator"
                        x-effect="updateOptions(creatorOptions)" />
                </div>

            </div>
        </form>
    </div>

    {{-- بطاقات إحصائية --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <p class="text-sm text-gray-500 mb-1">إجمالي التحصيلات</p>
            <p class="text-2xl font-black text-gray-800">{{ number_format($stats['total_rounds']) }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <p class="text-sm text-gray-500 mb-1">تحصيلات معلّقة</p>
            <p class="text-2xl font-black text-amber-600">{{ number_format($stats['total_pending']) }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <p class="text-sm text-gray-500 mb-1">إجمالي مؤكَّد</p>
            <p class="text-2xl font-black text-emerald-600">{{ number_format($stats['total_confirmed_amount'], 2) }} <span class="text-sm font-normal">جنيه</span></p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <p class="text-sm text-gray-500 mb-1">إجمالي معلّق</p>
            <p class="text-2xl font-black text-amber-600">{{ number_format($stats['total_pending_amount'], 2) }} <span class="text-sm font-normal">جنيه</span></p>
        </div>
    </div>

    {{-- جدول تحصيل الاشتراكات --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        @if($rounds->isEmpty())
        <div class="p-12 text-center">
            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <h3 class="text-lg font-bold text-gray-600 mb-1">لا توجد تحصيلات مسجَّلة</h3>
            <p class="text-gray-400 text-sm">يمكنك إنشاء تحصيل جديدة من الزر أعلاه</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-right">
                <thead class="bg-gray-50 border-b border-gray-100">
                    @php
                    $currentSort = $sort ?? 'created_at';
                    $currentDir = $direction ?? 'desc';

                    $sortLink = fn(string $col) =>
                    request()->fullUrlWithQuery([
                    'sort' => $col,
                    'direction' => ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc',
                    ]);

                    $sortIcon = function (string $col) use ($currentSort, $currentDir): string {
                    if ($currentSort !== $col) {
                    return '<svg class="w-3.5 h-3.5 opacity-30 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4" />
                    </svg>';
                    }
                    return $currentDir === 'asc'
                    ? '<svg class="w-3.5 h-3.5 text-[#0b3d2c] inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                    </svg>'
                    : '<svg class="w-3.5 h-3.5 text-[#0b3d2c] inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>';
                    };

                    $thBase = 'px-6 py-4 text-sm font-semibold cursor-pointer select-none hover:bg-gray-100 transition-colors';
                    $thActive = 'text-[#0b3d2c]';
                    $thMuted = 'text-gray-600';
                    @endphp
                    <tr>
                        <th class="px-6 py-4 text-sm font-semibold text-gray-600 w-10">#</th>
                        <th class="{{ $thBase }} {{ $currentSort === 'circle' ? $thActive : $thMuted }}"
                            onclick="window.location='{{ $sortLink('circle') }}'">
                            <div class="flex items-center justify-end gap-1">الحلقة {!! $sortIcon('circle') !!}</div>
                        </th>
                        <th class="{{ $thBase }} {{ $currentSort === 'center' ? $thActive : $thMuted }}"
                            onclick="window.location='{{ $sortLink('center') }}'">
                            <div class="flex items-center justify-end gap-1">المركز {!! $sortIcon('center') !!}</div>
                        </th>
                        <th class="{{ $thBase }} {{ $currentSort === 'period_month' ? $thActive : $thMuted }}"
                            onclick="window.location='{{ $sortLink('period_month') }}'">
                            <div class="flex items-center justify-end gap-1">الشهر {!! $sortIcon('period_month') !!}</div>
                        </th>
                        <th class="{{ $thBase }} {{ $currentSort === 'total_amount' ? $thActive : $thMuted }}"
                            onclick="window.location='{{ $sortLink('total_amount') }}'">
                            <div class="flex items-center justify-end gap-1">المبلغ {!! $sortIcon('total_amount') !!}</div>
                        </th>
                        <th class="px-6 py-4 text-sm font-semibold text-gray-600">الحالة</th>
                        <th class="px-6 py-4 text-sm font-semibold text-gray-600">المنشئ</th>
                        <th class="px-6 py-4 text-sm font-semibold text-gray-600 text-center w-16">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($rounds as $index => $round)
                    {{-- الصف الرئيسي --}}
                    <tr class="hover:bg-gray-50 transition cursor-pointer group"
                        onclick="toggleDetails({{ $round->id }})"
                        id="row-{{ $round->id }}">
                        <td class="px-6 py-4 text-sm text-gray-700 font-medium">{{ $rounds->firstItem() + $index }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ $round->circle?->name ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ $round->center?->name ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ $round->period_month ? (is_string($round->period_month) ? substr($round->period_month, 0, 7) : $round->period_month->format('Y-m')) : '—' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-800 font-black">{{ number_format($round->total_amount, 2) }} <span class="text-xs font-normal">ج.م</span></td>
                        <td class="px-6 py-4">
                            @if($round->status === 'pending')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700">
                                معلّق
                            </span>
                            @elseif($round->status === 'confirmed')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">
                                مؤكَّد
                            </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ $round->createdBy?->name ?? '—' }}</td>

                        {{-- ✅ عمود الإجراءات = زر توسيع فقط --}}
                        <td class="px-6 py-4 text-center">
                            <button type="button"
                                class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-[#0b3d2c] hover:text-white text-gray-400 transition flex items-center justify-center mx-auto"
                                onclick="event.stopPropagation(); toggleDetails({{ $round->id }})"
                                id="expand-btn-{{ $round->id }}">
                                <svg class="w-4 h-4 transform transition-transform duration-200" id="expand-icon-{{ $round->id }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </td>
                    </tr>

                    {{-- ✅ صف التفاصيل المخفي (expandable card) --}}
                    <tr id="details-{{ $round->id }}" class="hidden">
                        <td colspan="8" class="p-0">
                            <div class="bg-gray-50/50 border-b border-gray-100">
                                <div class="p-6">

                                    {{-- سهم التوسيع المركزي --}}
                                    <div class="flex items-center justify-center mb-4">
                                        <div class="w-8 h-8 rounded-full bg-white border border-gray-200 flex items-center justify-center shadow-sm cursor-pointer hover:bg-gray-50"
                                            onclick="toggleDetails({{ $round->id }})">
                                            <svg class="w-4 h-4 text-gray-400 transform rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </div>
                                    </div>

                                    {{-- بطاقة المعلومات --}}
                                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-0">

                                            {{-- معلومات إضافية (اليمين) --}}
                                            <div class="p-6 md:border-l border-gray-100">
                                                <h4 class="text-sm font-bold text-gray-800 mb-5 flex items-center justify-center gap-2">
                                                    <span>معلومات إضافية 📝</span>
                                                </h4>
                                                <div class="space-y-3 text-sm text-center">
                                                    <div class="flex justify-center items-center gap-2 py-1">
                                                        <span class="text-gray-500">رقم التحصيل:</span>
                                                        <span class="font-bold text-gray-800">#{{ $round->round_number }}</span>
                                                    </div>
                                                    <div class="flex justify-center items-center gap-2 py-1">
                                                        <span class="text-gray-500">تاريخ الإنشاء:</span>
                                                        <span class="font-medium text-gray-800 dir-ltr">{{ $round->created_at->format('Y-m-d H:i') }}</span>
                                                    </div>
                                                    <div class="flex justify-center items-center gap-2 py-1">
                                                        <span class="text-gray-500">آخر تحديث:</span>
                                                        <span class="font-medium text-gray-800 dir-ltr">{{ $round->updated_at->format('Y-m-d H:i') }}</span>
                                                    </div>
                                                    <div class="flex justify-center items-center gap-2 py-1">
                                                        <span class="text-gray-500">أكّد التحصيل:</span>
                                                        <span class="font-medium text-gray-800">{{ $round->confirmedBy?->name ?? '—' }}</span>
                                                    </div>
                                                    <div class="flex justify-center items-center gap-2 py-1">
                                                        <span class="text-gray-500">الفرع:</span>
                                                        <span class="font-medium text-gray-800">{{ $round->center?->name ?? '—' }}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- الملاحظات (اليسار) --}}
                                            <div class="p-6">
                                                <h4 class="text-sm font-bold text-gray-800 mb-5 flex items-center justify-center gap-2">
                                                    <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                                    </svg>
                                                    <span>الملاحظات</span>
                                                </h4>
                                                <div class="bg-gray-50 rounded-xl p-4 min-h-[140px] flex items-center justify-center">
                                                    @if($round->supervisor_note)
                                                    <p class="text-sm text-gray-700 leading-relaxed text-center">{{ $round->supervisor_note }}</p>
                                                    @else
                                                    <p class="text-sm text-gray-400 italic text-center">لا توجد ملاحظات</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        {{-- تحصيل التعديلات --}}
                                        @if($round->logs->isNotEmpty())
                                        <div class="border-t border-gray-100 p-6">
                                            <h4 class="text-sm font-bold text-gray-800 mb-4 flex items-center justify-center gap-2">
                                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>تحصيل التعديلات</span>
                                            </h4>
                                            <div class="space-y-2 max-h-48 overflow-y-auto">
                                                @foreach($round->logs as $log)
                                                <div class="bg-gray-50 rounded-lg p-3 text-sm flex items-start justify-between gap-3">
                                                    <div>
                                                        <p class="text-gray-700">{{ $log->description }}</p>
                                                        <p class="text-xs text-gray-400 mt-1">{{ $log->createdBy?->name ?? '—' }}</p>
                                                    </div>
                                                    <span class="text-xs text-gray-400 whitespace-nowrap dir-ltr">{{ $log->created_at->format('Y-m-d H:i') }}</span>
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        @endif

                                        {{-- خط فاصل + أزرار الإجراءات --}}
                                        <div class="border-t border-gray-100 p-4 flex items-center justify-between flex-wrap gap-3">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                {{-- زر عرض --}}
                                                @can('confirm', $round)
                                                <a href="{{ route('collection-rounds.confirm.show', $round->id) }}"
                                                    class="inline-flex items-center gap-2 px-4 py-2 bg-[#0b3d2c] text-white rounded-lg font-bold text-sm hover:bg-[#0a3324] transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                    عرض
                                                </a>
                                                @endcan

                                                {{-- زر تعديل --}}
                                                @can('update', $round)
                                                <a href="{{ route('collection-rounds.edit', $round->id) }}"
                                                    class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 text-white rounded-lg font-bold text-sm hover:bg-amber-600 transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                    تعديل
                                                </a>
                                                @endcan
                                            </div>

                                            {{-- زر حذف الاشتراك --}}
                                            @can('delete', $round)
                                            <form action="{{ route('collection-rounds.destroy', $round->id) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button"
                                                    onclick="confirmDelete(event, { name: 'التحصيل رقم {{ $round->round_number }}', type: 'التحصيل', form: this.closest('form') })"
                                                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-500 text-white rounded-xl font-bold text-sm hover:bg-red-600 transition shadow-sm">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                    حذف التحصيل 🗑️
                                                </button>
                                            </form>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($rounds->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            <x-pagination :paginator="$rounds" />
        </div>
        @endif
        @endif
    </div>

    <script>
        let searchTimeout;
        let openDetailsId = null;

        function debounceSearch(input) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                input.form.submit();
            }, 500);
        }

        function toggleDetails(id) {
            const detailsRow = document.getElementById('details-' + id);
            const icon = document.getElementById('expand-icon-' + id);
            const btn = document.getElementById('expand-btn-' + id);
            const row = document.getElementById('row-' + id);

            // Close previously opened details
            if (openDetailsId && openDetailsId !== id) {
                const prevDetails = document.getElementById('details-' + openDetailsId);
                const prevIcon = document.getElementById('expand-icon-' + openDetailsId);
                const prevBtn = document.getElementById('expand-btn-' + openDetailsId);
                const prevRow = document.getElementById('row-' + openDetailsId);

                if (prevDetails) prevDetails.classList.add('hidden');
                if (prevIcon) prevIcon.classList.remove('rotate-180');
                if (prevBtn) {
                    prevBtn.classList.remove('bg-[#0b3d2c]', 'text-white');
                    prevBtn.classList.add('bg-gray-100', 'text-gray-400');
                }
                if (prevRow) prevRow.classList.remove('bg-emerald-50/40');
            }

            if (detailsRow.classList.contains('hidden')) {
                // Open
                detailsRow.classList.remove('hidden');
                icon.classList.add('rotate-180');
                btn.classList.remove('bg-gray-100', 'text-gray-400');
                btn.classList.add('bg-[#0b3d2c]', 'text-white');
                row.classList.add('bg-emerald-50/40');
                openDetailsId = id;
            } else {
                // Close
                detailsRow.classList.add('hidden');
                icon.classList.remove('rotate-180');
                btn.classList.remove('bg-[#0b3d2c]', 'text-white');
                btn.classList.add('bg-gray-100', 'text-gray-400');
                row.classList.remove('bg-emerald-50/40');
                openDetailsId = null;
            }
        }

        // Close details when clicking outside
        document.addEventListener('click', function(e) {
            if (openDetailsId && !e.target.closest('table')) {
                const prevDetails = document.getElementById('details-' + openDetailsId);
                const prevIcon = document.getElementById('expand-icon-' + openDetailsId);
                const prevBtn = document.getElementById('expand-btn-' + openDetailsId);
                const prevRow = document.getElementById('row-' + openDetailsId);

                if (prevDetails) prevDetails.classList.add('hidden');
                if (prevIcon) prevIcon.classList.remove('rotate-180');
                if (prevBtn) {
                    prevBtn.classList.remove('bg-[#0b3d2c]', 'text-white');
                    prevBtn.classList.add('bg-gray-100', 'text-gray-400');
                }
                if (prevRow) prevRow.classList.remove('bg-emerald-50/40');
                openDetailsId = null;
            }
        });

        function collectionRoundFilters(config) {
            return {
                selectedCenter: config.initialCenter,
                selectedBranch: config.initialBranch,
                selectedCircle: config.initialCircle,
                selectedCreator: config.initialCreator,
                allCenters: config.allCenters,
                allBranches: config.allBranches,
                allCircles: config.allCircles,
                allCreators: config.allCreators,
                submitTimeout: null,

                get branchOptions() {
                    let filtered = this.allBranches;

                    if (this.selectedCenter) {
                        filtered = filtered.filter(b => String(b.center_id) === String(this.selectedCenter));
                    }

                    return filtered.map(b => ({
                        value: b.id,
                        label: b.name
                    }));
                },

                get circleOptions() {
                    let filtered = this.allCircles;

                    if (this.selectedCenter) {
                        filtered = filtered.filter(c => String(c.center_id) === String(this.selectedCenter));
                    }

                    if (this.selectedBranch) {
                        filtered = filtered.filter(c => String(c.branch_id) === String(this.selectedBranch));
                    }

                    if (this.selectedCreator) {
                        const creator = this.allCreators.find(c => String(c.id) === String(this.selectedCreator));
                        if (creator && creator.circle_ids) {
                            filtered = filtered.filter(c => creator.circle_ids.includes(c.id));
                        }
                    }

                    return filtered.map(c => ({
                        value: c.id,
                        label: c.name
                    }));
                },

                get creatorOptions() {
                    let filtered = this.allCreators;

                    if (this.selectedCenter) {
                        const circleIdsInCenter = this.allCircles
                            .filter(c => String(c.center_id) === String(this.selectedCenter))
                            .map(c => c.id);
                        filtered = filtered.filter(cr => {
                            if (!cr.circle_ids) return true;
                            return cr.circle_ids.some(id => circleIdsInCenter.includes(id));
                        });
                    }

                    if (this.selectedCircle) {
                        filtered = filtered.filter(cr => {
                            if (!cr.circle_ids) return true;
                            return cr.circle_ids.includes(this.selectedCircle);
                        });
                    }

                    return filtered.map(c => ({
                        value: c.id,
                        label: c.name
                    }));
                },

                init() {
                    this.$watch('selectedCenter', () => {
                        this.validateAndSubmit();
                    });

                    this.$watch('selectedBranch', () => {
                        this.validateAndSubmit();
                    });

                    this.$watch('selectedCircle', () => {
                        this.validateAndSubmit();
                    });

                    this.$watch('selectedCreator', () => {
                        this.validateAndSubmit();
                    });
                },

                validateAndSubmit() {
                    const validBranch = this.branchOptions.find(o => String(o.value) === String(this.selectedBranch));
                    if (this.selectedBranch && !validBranch) {
                        this.selectedBranch = '';
                    }

                    const validCircle = this.circleOptions.find(o => String(o.value) === String(this.selectedCircle));
                    if (this.selectedCircle && !validCircle) {
                        this.selectedCircle = '';
                    }

                    const validCreator = this.creatorOptions.find(o => String(o.value) === String(this.selectedCreator));
                    if (this.selectedCreator && !validCreator) {
                        this.selectedCreator = '';
                    }

                    clearTimeout(this.submitTimeout);
                    this.submitTimeout = setTimeout(() => {
                        document.getElementById('filterForm').submit();
                    }, 300);
                }
            };
        }
    </script>
</x-layouts.markaz-layout>