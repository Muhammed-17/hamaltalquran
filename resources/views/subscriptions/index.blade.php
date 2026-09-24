@php
$formattedMonth = \Carbon\Carbon::createFromFormat('Y-m', $statsMonth)->translatedFormat('F Y');
$columnsCount = 12;
@endphp
<x-layouts.markaz-layout>
    <div class="space-y-6">

        {{-- ─── Header ────────────────────────────────────────────── --}}
        <div class="bg-[#0b3d2c] rounded-3xl p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6">
            <div class="text-right w-full md:w-auto z-10 wrap-break-word">
                <h1 class="text-3xl font-black mb-2">إدارة اشتراكات الطلاب</h1>
                <p class="text-emerald-100/80 text-sm font-medium">متابعة التحصيل المالي والإحصائيات</p>
            </div>
            @can('create subscriptions')
            <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                <a href="{{ route('subscriptions.create') }}"
                    class="w-full md:w-auto px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-2xl flex items-center justify-center gap-2 transition-all shadow-lg hover:shadow-emerald-500/20 active:scale-95">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    تسجيل اشتراك جديد
                </a>

                {{-- ✅ زر الطلاب المتعثرين --}}
                <a href="{{ route('subscriptions.late_and_unpaid') }}"
                    class="w-full md:w-auto px-6 py-3 bg-red-500 hover:bg-red-400 text-white font-bold rounded-2xl flex items-center justify-center gap-2 transition-all shadow-lg active:scale-95">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    الطلاب المتعثرين
                </a>

                {{-- ✅ زر تحويل الأموال --}}
                @can('transfer subscriptions')
                <a href="{{ route('subscription-transfers.index') }}"
                    class="w-full md:w-auto px-6 py-3 bg-white/10 hover:bg-white/20 text-white font-bold rounded-2xl flex items-center justify-center gap-2 transition-all border border-white/20 active:scale-95">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m4 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    تحويل الأموال
                </a>
                @endcan
            </div>
            @endcan
        </div>

        {{-- ─── Filters ───────────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 mb-6"
            x-data="subscriptionFilters()"
            x-init="init()">

            <form method="GET" action="{{ route('subscriptions.index') }}" class="space-y-4" id="filterForm"
                @submit="applyFilters">

                {{-- الصف الأول: البحث والأزرار --}}
                <div class="flex flex-col md:flex-row gap-4 items-end pb-4 border-b border-gray-100">
                    <div class="w-full md:flex-1">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">بحث باسم الطالب</label>
                        <div class="relative">
                            <input type="text" name="search" value="{{ request('search', $search) }}"
                                placeholder="اكتب اسم الطالب للبحث بشكل سريع..."
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
                        @if($hasActiveFilters)
                        <a href="{{ route('subscriptions.index') }}"
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

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">

                    {{-- Month Filter --}}
                    <div class="w-full">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">الشهر</label>
                        <input type="month" name="month" value="{{ request('month') }}"
                            onchange="this.form.submit()"
                            class="w-full rounded-xl border-gray-200 focus:border-[#0b3d2c] focus:ring-[#0b3d2c] text-sm h-11">
                    </div>

                    {{-- فلتر الفرع (المركز) --}}
                    @if(auth()->user()->hasAnyRole(['admin', 'general_manager']))
                    @if($centers->count() > 0)
                    <div class="w-full">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">المركز</label>
                        <x-searchable-select
                            name="center_id"
                            placeholder="جميع المراكز"
                            search-placeholder="ابحث عن مركز..."
                            default-option="جميع المراكز"
                            default-value="{{ request('center_id', $selectedCenterId ?? '') }}"
                            :options="$centers->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values()"
                            x-model="selectedCenter" />
                    </div>
                    @endif
                    @endif

                    {{-- ✅ فلتر المقر (المركز المركزي) --}}
                    @if(auth()->user()->hasAnyRole(['admin', 'general_manager', 'manager']))
                    @if($branches->count() > 0)
                    <div class="w-full">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">المقر</label>
                        <x-searchable-select
                            name="branch_id"
                            placeholder="جميع المقرات"
                            search-placeholder="ابحث عن مقر..."
                            default-option="جميع المقرات"
                            default-value="{{ request('branch_id', $selectedBranchId ?? '') }}"
                            :options="$branches->map(fn($b) => ['value' => $b->id, 'label' => $b->name])->values()"
                            x-model="selectedBranch" />
                    </div>
                    @endif
                    @endif

                    {{-- فلتر الحلقة --}}
                    <div class="w-full">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">الحلقة</label>
                        <x-searchable-select
                            name="circle_id"
                            placeholder="جميع الحلقات"
                            search-placeholder="ابحث عن حلقة..."
                            default-option="جميع الحلقات"
                            default-value="{{ request('circle_id', $selectedCircleId ?? '') }}"
                            :options="$circles->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values()" />
                    </div>

                    {{-- فلتر الحالة --}}
                    <div class="w-full">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">الحالة</label>
                        <select name="status" onchange="this.form.submit()"
                            class="w-full rounded-xl border-gray-200 focus:border-[#0b3d2c] focus:ring-[#0b3d2c] text-sm h-11">
                            <option value="">جميع الحالات</option>
                            <option value="مدفوع" {{ request('status', $selectedStatus) == 'مدفوع' ? 'selected' : '' }}>مدفوع</option>
                            <option value="معفي" {{ request('status', $selectedStatus) == 'معفي' ? 'selected' : '' }}>معفي</option>
                        </select>
                    </div>

                    {{-- فلتر المعلم --}}
                    @if(auth()->user()->hasAnyRole(['admin', 'general_manager', 'manager', 'supervisor']))
                    <div class="w-full sm:col-span-2 md:col-span-1">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">المعلم</label>
                        <x-searchable-select
                            name="teacher_id"
                            placeholder="جميع المعلمين"
                            search-placeholder="ابحث عن معلم..."
                            default-option="جميع المعلمين"
                            default-value="{{ request('teacher_id', $selectedTeacherId ?? '') }}"
                            :options="$teachers->map(fn($t) => ['value' => $t->id, 'label' => $t->name])->values()" />
                    </div>
                    @endif

                    {{-- ✅ فلتر المحصِّل --}}
                    @if($collectedByUsers->isNotEmpty())
                    <div class="w-full sm:col-span-2 md:col-span-1">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">المحصِّل</label>
                        <x-searchable-select
                            name="collected_by_id"
                            placeholder="جميع المحصِّلين"
                            search-placeholder="ابحث عن محصِّل..."
                            default-option="جميع المحصِّلين"
                            default-value="{{ request('collected_by_id', $selectedCollectedById ?? '') }}"
                            :options="$collectedByUsers->map(fn($u) => ['value' => $u->id, 'label' => $u->name])->values()" />
                    </div>
                    @endif

                </div>
            </form>
        </div>

        {{-- ─── Statistics Cards ──────────────────────────────────── --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            {{-- التحصيل الفعلي في الشهر المختار --}}
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-4 group hover:border-emerald-100 transition-all">
                <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 transition-transform group-hover:scale-110">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-gray-400 text-xs font-bold">
                        تحصيل شهر {{ $formattedMonth }}
                    </p>

                    {{-- إجمالي التحصيل الفعلي في الشهر --}}
                    <h3 class="text-2xl font-black text-gray-800">
                        {{ number_format($monthlyCollected, 2) }}
                        <span class="text-sm font-medium text-gray-400">ج.م</span>
                    </h3>

                    {{-- عدد من دفع فعلياً --}}
                    <p class="text-xs text-gray-400 mt-0.5">
                        عدد الدفعات الفعلية:
                        <span class="font-bold text-emerald-600">{{ $paidMonthCount ?? 0 }} دفعة</span>
                    </p>

                    {{-- إيرادات هذا الشهر فقط بدون الأشهر الأخرى --}}
                    <p class="text-xs text-gray-400 mt-0.5">
                        إيرادات هذا الشهر فقط:
                        <span class="font-bold text-gray-700">{{ number_format($dueMonthRevenue ?? 0, 2) }} ج.م</span>
                    </p>

                    {{-- إيرادات أشهر أخرى دُفعت في هذا الشهر --}}
                    @if(($collectedForOtherMonths ?? 0) > 0)
                    <p class="text-xs text-amber-500 mt-0.5">
                        دفعات متأخرة من أشهر سابقة:
                        <span class="font-bold">{{ number_format($collectedForOtherMonths, 2) }} ج.م</span>
                    </p>
                    @endif
                </div>
            </div>

            {{-- نسبة السداد --}}
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-4 group hover:border-blue-100 transition-all">
                <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-600 transition-transform group-hover:scale-110">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                    </svg>
                </div>
                <div>
                    <p class="text-gray-400 text-xs font-bold">نسبة السداد</p>

                    {{-- النسبة الإجمالية: مدفوع + معفي --}}
                    <h3 class="text-2xl font-black text-gray-800">{{ $paidAndExemptRate }}%</h3>
                    {{-- نسبة فرعية: مدفوع فقط --}}
                    <p class="text-xs text-gray-400 mt-0.5">
                        المدفوع فقط:
                        <span class="font-bold text-emerald-600">{{ $paymentRate }}%</span>
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5">
                        المعفي فقط:
                        <span class="font-bold text-emerald-600">{{ $paidAndExemptRate -$paymentRate }}%</span>
                    </p>
                </div>
            </div>

            {{-- المبالغ غير المحصلة --}}
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-4 group hover:border-amber-100 transition-all">
                <div class="w-14 h-14 bg-amber-50 rounded-2xl flex items-center justify-center text-amber-600 transition-transform group-hover:scale-110">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-gray-400 text-xs font-bold">إجمالي المبالغ المستحقة (غير محصلة)</p>
                    <h3 class="text-2xl font-black text-gray-800">
                        {{ number_format($unpaidAmount ?? 0, 2) }}
                        <span class="text-sm font-medium text-gray-400">ج.م</span>
                    </h3>
                </div>
            </div>
            {{-- البطاقة 1: المدفوعون --}}
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-4 group hover:border-emerald-100 transition-all">
                <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 transition-transform group-hover:scale-110">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-gray-400 text-xs font-bold">
                        الطلاب المدفوعون
                        <span class="text-emerald-600">
                            - {{ $formattedMonth }}
                        </span>
                    </p>
                    <h3 class="text-2xl font-black text-gray-800">
                        {{ $paidOnlyCount }}
                        <span class="text-sm font-medium text-gray-400">من {{ $totalActiveStudents }}</span>
                    </h3>
                </div>
            </div>

            {{-- البطاقة 2: المعفيون --}}
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-4 group hover:border-blue-100 transition-all">
                <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-600 transition-transform group-hover:scale-110">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <div>
                    <p class="text-gray-400 text-xs font-bold">
                        الطلاب المعفيون
                        <span class="text-blue-600">
                            - {{ $formattedMonth }}
                        </span>
                    </p>
                    <h3 class="text-2xl font-black text-gray-800">
                        {{ $exemptOnlyCount }}
                        <span class="text-sm font-medium text-gray-400">من {{ $totalActiveStudents }}</span>
                    </h3>
                </div>
            </div>

            {{-- البطاقة 3: الإجمالي --}}
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-4 group hover:border-amber-100 transition-all">
                <div class="w-14 h-14 bg-amber-50 rounded-2xl flex items-center justify-center text-amber-600 transition-transform group-hover:scale-110">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-gray-400 text-xs font-bold">
                        إجمالي المسددون والمعفيون
                        <span class="text-amber-600">
                            - {{ $formattedMonth }}
                        </span>
                    </p>
                    <h3 class="text-2xl font-black text-gray-800">
                        {{ $paidOrExemptCount }}
                        <span class="text-sm font-medium text-gray-400">من {{ $totalActiveStudents }}</span>
                    </h3>
                    <p class="text-xs text-gray-400 mt-1">
                        المتبقي:
                        <span class="font-bold text-red-500">{{ $totalActiveStudents - $paidOrExemptCount }} طالب</span>
                    </p>
                </div>
            </div>

        </div>

        {{-- ─── Monthly Revenue Chart ─────────────────────────────── --}}
        @can('view subscriptions chart')
        @if($monthlyRevenue->isNotEmpty())
        <div x-data="{ open: true }" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

            <button @click="open = !open"
                class="w-full flex items-center justify-between p-5 hover:bg-gray-50 transition-colors">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-emerald-50 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <div class="text-right">
                        <h3 class="text-base font-bold text-gray-800">التحصيل الشهري</h3>
                        <p class="text-xs text-gray-400">آخر {{ $monthlyRevenue->count() }} أشهر</p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400 transition-transform duration-200"
                    :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div x-show="open" class="px-6 pb-6">
                <div class="relative h-64">
                    <canvas id="monthlyRevenueChart"></canvas>
                </div>
            </div>

        </div>
        @endif

        {{-- ─── Monthly Payment Rate Chart ────────────────────────── --}}
        @if($monthlyPaymentStats->isNotEmpty())
        <div x-data="{ open: true }" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

            <button @click="open = !open"
                class="w-full flex items-center justify-between p-5 hover:bg-gray-50 transition-colors">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-blue-50 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="text-right">
                        <h3 class="text-base font-bold text-gray-800">نسبة عدد الطلاب الذين دفعوا</h3>
                        <p class="text-xs text-gray-400">آخر {{ $monthlyPaymentStats->count() }} أشهر - عدد المدفوعين من الإجمالي</p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400 transition-transform duration-200"
                    :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div x-show="open" class="px-6 pb-6">
                <div class="relative h-64">
                    <canvas id="monthlyPaymentChart"></canvas>
                </div>
            </div>

        </div>
        @endif
        @endcan

        {{-- ─── Subscriptions Table ───────────────────────────────── --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            {{-- ✅ شارة المركز المختار - ضعها هنا في الهيدر --}}
            <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-800">سجل الاشتراكات</h2>
                @if($selectedCenterId && isset($centers))
                @php $selectedCenter = $centers->firstWhere('id', $selectedCenterId); @endphp
                @if($selectedCenter)
                <span class="bg-emerald-100 text-emerald-800 px-3 py-1 rounded-full text-sm font-medium">
                    {{ $selectedCenter->name }}
                </span>
                @endif
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right">
                    <thead class="bg-gray-50">
                        @php
                        $currentSort = $sort ?? 'paid_at';
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
                            <th class="px-6 py-4 text-sm font-semibold text-gray-600">#</th>
                            <th class="{{ $thBase }} {{ $currentSort === 'student' ? $thActive : $thMuted }}"
                                onclick="window.location='{{ $sortLink('student') }}'">
                                <div class="flex items-center justify-end gap-1">الطالب {!! $sortIcon('student') !!}</div>
                            </th>
                            <th class="{{ $thBase }} {{ $currentSort === 'circle' ? $thActive : $thMuted }}"
                                onclick="window.location='{{ $sortLink('circle') }}'">
                                <div class="flex items-center justify-end gap-1">الحلقة {!! $sortIcon('circle') !!}</div>
                            </th>
                            <th class="{{ $thBase }} {{ $currentSort === 'center' ? $thActive : $thMuted }}"
                                onclick="window.location='{{ $sortLink('center') }}'">
                                <div class="flex items-center justify-end gap-1">المركز {!! $sortIcon('center') !!}</div>
                            </th>
                            <th class="{{ $thBase }} {{ $currentSort === 'branch' ? $thActive : $thMuted }}"
                                onclick="window.location='{{ $sortLink('branch') }}'">
                                <div class="flex items-center justify-end gap-1">المقر {!! $sortIcon('branch') !!}</div>
                            </th>
                            <th class="{{ $thBase }} {{ $currentSort === 'month' ? $thActive : $thMuted }}"
                                onclick="window.location='{{ $sortLink('month') }}'">
                                <div class="flex items-center justify-end gap-1">الشهر {!! $sortIcon('month') !!}</div>
                            </th>
                            <th class="{{ $thBase }} {{ $currentSort === 'amount' ? $thActive : $thMuted }}"
                                onclick="window.location='{{ $sortLink('amount') }}'">
                                <div class="flex items-center justify-end gap-1">المبلغ {!! $sortIcon('amount') !!}</div>
                            </th>
                            <th class="{{ $thBase }} {{ $currentSort === 'status' ? $thActive : $thMuted }}"
                                onclick="window.location='{{ $sortLink('status') }}'">
                                <div class="flex items-center justify-end gap-1">الحالة {!! $sortIcon('status') !!}</div>
                            </th>
                            <th class="{{ $thBase }} {{ $currentSort === 'payment_method' ? $thActive : $thMuted }}"
                                onclick="window.location='{{ $sortLink('payment_method') }}'">
                                <div class="flex items-center justify-end gap-1">طريقة الدفع {!! $sortIcon('payment_method') !!}</div>
                            </th>
                            <th class="{{ $thBase }} {{ $currentSort === 'paid_at' ? $thActive : $thMuted }}"
                                onclick="window.location='{{ $sortLink('paid_at') }}'">
                                <div class="flex items-center justify-end gap-1">تاريخ الدفع {!! $sortIcon('paid_at') !!}</div>
                            </th>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-600">المحصل</th>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-600">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($recentSubscriptions as $index => $subscription)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 text-gray-400 text-sm font-medium">
                                {{ $recentSubscriptions->firstItem() + $index }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900 flex items-center gap-2">
                                    {{ $subscription->student->name ?? '—' }}
                                    {{-- علامة الملاحظات --}}
                                    @if(!empty($subscription->notes))
                                    <span class="text-amber-500 cursor-help" title="{{ Str::limit($subscription->notes, 100) }}">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ $subscription->circle->name ?? '—' }}</td>
                            {{-- ✅ عمود المركز في صف البيانات - أضفه هنا --}}
                            <td class="px-6 py-4 text-gray-600">{{ $subscription->circle->center->name ?? '—' }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ $subscription->circle->branch->name ?? '—' }}</td>
                            <td class="px-6 py-4 text-gray-600">
                                {{ \Carbon\Carbon::parse($subscription->month)->translatedFormat('F Y') }}
                            </td>
                            <td class="px-6 py-4 font-bold text-gray-800">{{ number_format($subscription->amount, 2) }} ج.م</td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-1">
                                    @if($subscription->status == 'مدفوع')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                        مدفوع
                                    </span>
                                    @elseif($subscription->status == 'معفي')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        معفي
                                    </span>
                                    @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        غير مدفوع
                                    </span>
                                    @endif

                                    {{-- ✅ Badge: جزء من التحصيل مؤكَّد --}}
                                    @if($subscription->collectionRoundItem && $subscription->collectionRoundItem->collectionRound?->status === 'confirmed')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200"
                                        title="التحصيل رقم {{ $subscription->collectionRoundItem->collectionRound->round_number }}">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                        محمي
                                    </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ $subscription->payment_method ?? '—' }}</td>
                            <td class="px-6 py-4 text-gray-600">
                                @if($subscription->paid_at)
                                {{ $subscription->paid_at->format('Y-m-d') }}
                                @elseif($subscription->status === 'معفي')
                                {{ $subscription->created_at->format('Y-m-d') }}
                                @else
                                —
                                @endif
                            </td>
                            {{-- ✅ عمود المحصِّل — مباشر بدون شروط --}}
                            <td class="px-6 py-4 text-gray-600">
                                {{ $subscription->collectedBy->name ?? '—' }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <button onclick="toggleDetails({{ $subscription->id }})"
                                        class="flex items-center gap-1.5 px-3 py-2 rounded-xl transition-colors
                {{ !empty($subscription->notes) 
                    ? 'bg-amber-50 hover:bg-amber-100 text-amber-600 border border-amber-200' 
                    : 'bg-gray-50 hover:bg-gray-100 text-gray-500' }}"
                                        title="{{ !empty($subscription->notes) ? 'توجد ملاحظات - اضغط للعرض' : 'التفاصيل' }}"
                                        aria-label="عرض تفاصيل الاشتراك"
                                        aria-expanded="false"
                                        id="btn-{{ $subscription->id }}">
                                        @if(!empty($subscription->notes))
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                        </svg>
                                        <span class="text-xs font-bold">ملاحظة</span>
                                        @endif
                                        <svg class="w-4 h-4 transform transition-transform" id="icon-{{ $subscription->id }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        {{-- صف التفاصيل المخفي --}}
                        <tr id="details-{{ $subscription->id }}" class="hidden bg-gray-50/50">
                            {{-- ✅ غيّر colspan من 9 إلى 10 بسبب إضافة عمود المركز --}}
                            <td colspan="{{ $columnsCount }}" class="px-6 py-4">
                                <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {{-- الملاحظات --}}
                                        <div>
                                            <h4 class="text-sm font-bold text-gray-700 mb-2 flex items-center gap-2">
                                                <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                                </svg>
                                                الملاحظات:
                                            </h4>
                                            <p class="text-sm text-gray-600 bg-gray-50 rounded-lg p-3 {{ empty($subscription->notes) ? 'text-gray-400 italic' : '' }}">
                                                {{ $subscription->notes ?? 'لا توجد ملاحظات' }}
                                            </p>
                                        </div>

                                        {{-- معلومات إضافية --}}
                                        <div class="space-y-2">
                                            <h4 class="text-sm font-bold text-gray-700 mb-2">📋 معلومات إضافية:</h4>
                                            <div class="text-sm text-gray-600 space-y-1">
                                                <p><span class="font-medium">رقم الاشتراك:</span> #{{ $subscription->id }}</p>
                                                <p><span class="font-medium">تاريخ الإنشاء:</span> {{ $subscription->created_at->format('Y-m-d H:i') }}</p>
                                                <p><span class="font-medium">آخر تحديث:</span> {{ $subscription->updated_at->format('Y-m-d H:i') }}</p>
                                                <p><span class="font-medium">المعلم:</span> {{ $subscription->teacher->name ?? '—' }}</p>
                                                <p><span class="font-medium">الفرع:</span> {{ $subscription->circle->center->name ?? '—' }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- ✅ أزرار التعديل والحذف --}}
                                    <div class="mt-4 flex gap-2 justify-end border-t border-gray-100 pt-4">
                                        <!-- @can('edit subscriptions')
                                        <a href="{{ route('subscriptions.edit', $subscription) }}"
                                            class="bg-[#0b3d2c] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3324] transition flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            تعديل
                                        </a>
                                        @endcan -->
                                        @can('delete subscriptions')
                                        {{-- ✅ يظهر زر الحذف فقط إذا لم يكن الاشتراك مرتبطاً بأي جولة تحصيل --}}
                                        @if(!$subscription->collectionRoundItem)
                                        <form action="{{ route('subscriptions.destroy', $subscription) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button"
                                                onclick="confirmDelete(event, { name: '{{ addslashes($subscription->student->name ?? '') }}', type: 'اشتراك', form: this.closest('form') })"
                                                class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                حذف الاشتراك
                                            </button>
                                        </form>
                                        @else
                                        {{-- الاشتراك مرتبط بجولة تحصيل — لا يظهر زر الحذف --}}
                                        @endif
                                        @endcan
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        {{-- ✅ غيّر colspan من 9 إلى 10 --}}
                        <tr>
                            <td colspan="{{ $columnsCount }}" class="px-6 py-12 text-center text-gray-500">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="text-lg font-medium">لا توجد اشتراكات مسجلة</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <x-pagination :paginator="$recentSubscriptions" />
        </div>
    </div>

    <script>
        let openId = null;
        let searchTimeout;

        function toggleDetails(id) {
            const detailsRow = document.getElementById('details-' + id);
            const icon = document.getElementById('icon-' + id);
            const btn = document.getElementById('btn-' + id);

            if (openId && openId !== id) {
                document.getElementById('details-' + openId).classList.add('hidden');
                document.getElementById('icon-' + openId).classList.remove('rotate-180');
                const prevBtn = document.getElementById('btn-' + openId);
                if (prevBtn) prevBtn.setAttribute('aria-expanded', 'false');
            }

            if (detailsRow.classList.contains('hidden')) {
                detailsRow.classList.remove('hidden');
                icon.classList.add('rotate-180');
                btn.setAttribute('aria-expanded', 'true');
                openId = id;
            } else {
                detailsRow.classList.add('hidden');
                icon.classList.remove('rotate-180');
                btn.setAttribute('aria-expanded', 'false');
                openId = null;
            }
        }

        document.addEventListener('click', function(e) {
            if (openId && !e.target.closest('table')) {
                document.getElementById('details-' + openId).classList.add('hidden');
                document.getElementById('icon-' + openId).classList.remove('rotate-180');
                const btn = document.getElementById('btn-' + openId);
                if (btn) btn.setAttribute('aria-expanded', 'false');
                openId = null;
            }
        });

        function debounceSearch(input) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                input.form.submit();
            }, 500);
        }

        @can('view subscriptions chart')
        @if($monthlyRevenue->isNotEmpty())
            (function() {
                const raw = @json($monthlyRevenue);

                const labels = raw.map(d => {
                    const [year, month] = d.month_label.split('-');
                    const date = new Date(year, month - 1);
                    return date.toLocaleDateString('ar-EG', {
                        month: 'short',
                        year: 'numeric'
                    });
                });

                const values = raw.map(d => parseFloat(d.total));

                document.addEventListener('DOMContentLoaded', function() {
                    Alpine.nextTick(() => {
                        const ctx = document.getElementById('monthlyRevenueChart');
                        if (!ctx) return;

                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels,
                                datasets: [{
                                    label: 'التحصيل (ج.م)',
                                    data: values,
                                    backgroundColor: 'rgba(16, 185, 129, 0.15)',
                                    borderColor: '#10b981',
                                    borderWidth: 2,
                                    borderRadius: 8,
                                    borderSkipped: false,
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: ctx => ' ' + ctx.parsed.y.toLocaleString('ar-EG') + ' ج.م'
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        grid: {
                                            color: '#f3f4f6'
                                        },
                                        ticks: {
                                            callback: val => val.toLocaleString('ar-EG') + ' ج.م'
                                        }
                                    },
                                    x: {
                                        grid: {
                                            display: false
                                        }
                                    }
                                }
                            }
                        });
                    });
                });
            })();
        @endif

        @if($monthlyPaymentStats->isNotEmpty())
            (function() {
                const raw = @json($monthlyPaymentStats);

                const labels = raw.map(d => {
                    const [year, month] = d.month_label.split('-');
                    const date = new Date(year, month - 1);
                    return date.toLocaleDateString('ar-EG', {
                        month: 'short',
                        year: 'numeric'
                    });
                });

                const paidCounts = raw.map(d => d.paid_count);
                const exemptCounts = raw.map(d => d.exempt_count);
                const unpaidCounts = raw.map(d => d.unpaid_count);
                const totalCounts = raw.map(d => d.total_count);

                document.addEventListener('DOMContentLoaded', function() {
                    Alpine.nextTick(() => {
                        const ctx = document.getElementById('monthlyPaymentChart');
                        if (!ctx) return;

                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels,
                                datasets: [{
                                        label: 'مدفوع',
                                        data: paidCounts,
                                        backgroundColor: 'rgba(16, 185, 129, 0.7)',
                                        borderColor: '#10b981',
                                        borderWidth: 1,
                                        borderRadius: 4,
                                    },
                                    {
                                        label: 'معفي',
                                        data: exemptCounts,
                                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                                        borderColor: '#3b82f6',
                                        borderWidth: 1,
                                        borderRadius: 4,
                                    },
                                    {
                                        label: 'غير مدفوع',
                                        data: unpaidCounts,
                                        backgroundColor: 'rgba(239, 68, 68, 0.7)',
                                        borderColor: '#ef4444',
                                        borderWidth: 1,
                                        borderRadius: 4,
                                    },
                                    {
                                        label: 'الإجمالي',
                                        data: totalCounts,
                                        type: 'line',
                                        borderColor: '#9ca3af',
                                        borderDash: [5, 5],
                                        borderWidth: 2,
                                        pointRadius: 3,
                                        fill: false,
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: true,
                                        position: 'top'
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: ctx => {
                                                const total = totalCounts[ctx.dataIndex];
                                                const percentage = total > 0 ? ((ctx.parsed.y / total) * 100).toFixed(1) : 0;
                                                return ` ${ctx.dataset.label}: ${ctx.parsed.y} (${percentage}%)`;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        grid: {
                                            color: '#f3f4f6'
                                        },
                                        ticks: {
                                            precision: 0
                                        }
                                    },
                                    x: {
                                        grid: {
                                            display: false
                                        }
                                    }
                                }
                            }
                        });
                    });
                });
            })();
        @endif
        @endcan

        function subscriptionFilters() {
            return {
                selectedCenter: '{{ request("center_id",       $selectedCenterId      ?? "") }}',
                selectedBranch: '{{ request("branch_id",       $selectedBranchId      ?? "") }}', // ✅ جديد
                selectedCircle: '{{ request("circle_id",       $selectedCircleId      ?? "") }}',
                selectedTeacher: '{{ request("teacher_id",      $selectedTeacherId     ?? "") }}',
                selectedCollectedBy: '{{ request("collected_by_id", $selectedCollectedById ?? "") }}',

                centers: @json($centers),
                branches: @json($branches),
                circles: @json($circles),
                teachers: @json($teachers),
                collectedByUsers: @json($collectedByUsers),

                filterUrl: '{{ route("subscriptions.filter-options") }}',

                init() {
                    this.$watch('selectedCenter', () => {
                        this.onCenterChange();
                    });

                    // ✅ جديد: راقب تغيّر المقر
                    this.$watch('selectedBranch', () => {
                        this.onBranchChange();
                    });

                    window.addEventListener('searchable-change', (e) => {
                        if (e.detail.name === 'branch_id') {
                            this.selectedBranch = e.detail.value;
                        }
                        if (e.detail.name === 'circle_id') {
                            this.selectedCircle = e.detail.value;
                            this.onCircleChange();
                        }
                        if (e.detail.name === 'teacher_id') {
                            this.selectedTeacher = e.detail.value;
                            this.onTeacherChange();
                        }
                        if (e.detail.name === 'collected_by_id') {
                            this.selectedCollectedBy = e.detail.value;
                        }
                    });

                    if (this.selectedCenter || this.selectedBranch || this.selectedCircle || this.selectedTeacher || this.selectedCollectedBy) {
                        this.fetchOptions();
                    }
                },

                async fetchOptions() {
                    const params = new URLSearchParams();
                    if (this.selectedCenter) params.append('center_id', this.selectedCenter);
                    if (this.selectedBranch) params.append('branch_id', this.selectedBranch); // ✅ جديد
                    if (this.selectedCircle) params.append('circle_id', this.selectedCircle);
                    if (this.selectedTeacher) params.append('teacher_id', this.selectedTeacher);

                    try {
                        const res = await fetch(`${this.filterUrl}?${params}`);
                        const data = await res.json();

                        this.centers = data.centers;
                        this.branches = data.branches; // ✅ جديد
                        this.circles = data.circles;
                        this.teachers = data.teachers;
                        this.collectedByUsers = data.collected_by;

                        // ✅ جديد
                        window.dispatchEvent(new CustomEvent('update-options', {
                            detail: {
                                name: 'branch_id',
                                options: data.branches.map(b => ({
                                    value: b.id,
                                    label: b.name
                                }))
                            }
                        }));
                        window.dispatchEvent(new CustomEvent('update-options', {
                            detail: {
                                name: 'circle_id',
                                options: data.circles.map(c => ({
                                    value: c.id,
                                    label: c.name
                                }))
                            }
                        }));
                        window.dispatchEvent(new CustomEvent('update-options', {
                            detail: {
                                name: 'teacher_id',
                                options: data.teachers.map(t => ({
                                    value: t.id,
                                    label: t.name
                                }))
                            }
                        }));
                        window.dispatchEvent(new CustomEvent('update-options', {
                            detail: {
                                name: 'collected_by_id',
                                options: data.collected_by.map(u => ({
                                    value: u.id,
                                    label: u.name
                                }))
                            }
                        }));

                        if (!data.centers.find(c => c.id == this.selectedCenter)) this.selectedCenter = '';
                        // ✅ جديد — لو المقر المختار مش في القائمة الجديدة يُصفَّر
                        if (!data.branches.find(b => b.id == this.selectedBranch)) this.selectedBranch = '';
                        if (!data.circles.find(c => c.id == this.selectedCircle)) this.selectedCircle = '';
                        if (!data.teachers.find(t => t.id == this.selectedTeacher)) this.selectedTeacher = '';
                        if (!data.collected_by.find(u => u.id == this.selectedCollectedBy)) this.selectedCollectedBy = '';

                    } catch (e) {
                        console.error('Filter fetch error:', e);
                    }
                },

                onCenterChange() {
                    this.selectedBranch = ''; // ✅ جديد
                    this.selectedCircle = '';
                    this.selectedTeacher = '';
                    this.selectedCollectedBy = '';
                    this.fetchOptions();
                },
                // ✅ جديد
                onBranchChange() {
                    this.selectedCircle = '';
                    this.selectedTeacher = '';
                    this.selectedCollectedBy = '';
                    this.fetchOptions();
                },
                onCircleChange() {
                    this.selectedTeacher = '';
                    this.selectedCollectedBy = '';
                    this.fetchOptions();
                },
                onTeacherChange() {
                    this.selectedCircle = '';
                    this.fetchOptions();
                },
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const params = new URLSearchParams(window.location.search);
            const transferMessage = params.get('transfer_success');
            if (transferMessage) {
                showSuccess(transferMessage);
                // ✅ تنظيف الـ URL من الـ query param بعد العرض، عشان الرسالة متتكررش لو المستخدم عمل refresh
                params.delete('transfer_success');
                const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
                window.history.replaceState({}, '', newUrl);
            }
        });
    </script>
</x-layouts.markaz-layout>