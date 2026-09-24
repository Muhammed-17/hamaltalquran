<x-layouts.markaz-layout>
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 shadow-xl">
            <div class="relative z-10">
                <h2 class="text-2xl font-bold text-white flex items-center gap-3">
                    <span class="w-10 h-10 rounded-lg bg-white/10 text-white flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </span>
                    المتابعات الأسبوعية — فردي
                </h2>
                <p class="text-emerald-100/80 mt-1 text-sm">إدارة خطط الحفظ والمراجعة الأسبوعية لكل طالب</p>
            </div>
            <div class="flex items-center gap-3 relative z-10">
                @can('create student weekly followups')
                <a href="{{ route('student-weekly-followups.create-individual') }}"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white font-bold transition-colors shadow-lg hover:shadow-emerald-500/20 active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    متابعة فردية جديدة
                </a>
                @endcan
                @if($circleAccess['group'])
                <a href="{{ route('student-weekly-followups.index-group') }}"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-white/10 hover:bg-white/20 text-white font-bold transition-all border border-white/20 active:scale-95">
                    عرض المتابعات الجماعية
                </a>
                @endif
            </div>

            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">إجمالي المتابعات</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['total'] }}</p>
                    </div>
                    <span class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">المتابعات الفردية</p>
                        <p class="text-2xl font-bold text-sky-600 mt-1">{{ $stats['individual'] }}</p>
                    </div>
                    <span class="w-10 h-10 rounded-lg bg-sky-100 flex items-center justify-center text-sky-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">هذا الأسبوع</p>
                        <p class="text-2xl font-bold text-amber-600 mt-1">{{ $stats['this_week'] }}</p>
                    </div>
                    <span class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center text-amber-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5"
            x-data="weeklyFollowupFilters({
        initialCenter: '{{ request('center_id', '') }}',
        initialBranch: '{{ request('branch_id', '') }}',
        initialCircle: '{{ request('circle_id', '') }}',
        allBranches: {{ Js::from($filters['branches']) }},
        allCircles: {{ Js::from($filters['circles']) }},
    })">
            <form method="GET" action="{{ route('student-weekly-followups.index-individual') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-7 gap-3">
                @if (auth()->user()->hasAnyRole(['admin', 'general_manager']))
                <x-searchable-select
                    name="center_id"
                    :options="$filters['centers']->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values()"
                    placeholder="كل المراكز"
                    searchPlaceholder="ابحث عن مركز..."
                    :defaultValue="request('center_id', '')"
                    x-model="selectedCenter" />
                @endif

                <div x-effect="updateOptions('branch_id', branchOptions)">
                    <x-searchable-select
                        name="branch_id"
                        :options="$filters['branches']->map(fn($b) => ['value' => $b->id, 'label' => $b->name])->values()"
                        placeholder="كل المقرات"
                        searchPlaceholder="ابحث عن مقر..."
                        :defaultValue="request('branch_id', '')"
                        x-model="selectedBranch" />
                </div>

                <div x-effect="updateOptions('circle_id', circleOptions)">
                    <x-searchable-select
                        name="circle_id"
                        :options="$filters['circles']->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values()"
                        placeholder="كل الحلقات"
                        searchPlaceholder="ابحث عن حلقة..."
                        :defaultValue="request('circle_id', '')"
                        x-model="selectedCircle" />
                </div>

                @if (auth()->user()->hasAnyRole(['admin', 'general_manager', 'manager', 'supervisor']))
                <x-searchable-select
                    name="teacher_id"
                    :options="$filters['teachers']->map(fn($t) => ['value' => $t->id, 'label' => $t->user->name ?? $t->name])->values()"
                    placeholder="كل المعلمين"
                    searchPlaceholder="ابحث عن معلم..."
                    :defaultValue="request('teacher_id', '')" />
                @endif
                <input type="date" name="week_start"
                    class="rounded-lg border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm"
                    value="{{ request('week_start') }}" placeholder="من تاريخ">
                <input type="date" name="week_end"
                    class="rounded-lg border-gray-300 focus:border-[#0a5c36] focus:ring-[#0a5c36] text-sm"
                    value="{{ request('week_end') }}" placeholder="إلى تاريخ">
                <button type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#0a5c36] text-white px-4 py-2 text-sm font-medium hover:bg-[#0d7a48] transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    تصفية
                </button>
            </form>
        </div>

        @include('student_weekly_followups.index_table', [
        'planType' => 'individual',
        ])
    </div>
    <script>
        function weeklyFollowupFilters(config) {
            return {
                selectedCenter: config.initialCenter,
                selectedBranch: config.initialBranch,
                selectedCircle: config.initialCircle,
                allBranches: config.allBranches,
                allCircles: config.allCircles,

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
                    return filtered.map(c => ({
                        value: c.id,
                        label: c.name
                    }));
                },

                init() {
                    window.addEventListener('searchable-change', (e) => {
                        if (e.detail.name === 'center_id') {
                            this.selectedCenter = e.detail.value;
                            this.validateBranch();
                            this.validateCircle();
                        }
                        if (e.detail.name === 'branch_id') {
                            this.selectedBranch = e.detail.value;
                            this.validateCircle();
                        }
                        if (e.detail.name === 'circle_id') {
                            this.selectedCircle = e.detail.value;
                        }
                    });
                },

                validateBranch() {
                    const valid = this.branchOptions.find(o => String(o.value) === String(this.selectedBranch));
                    if (this.selectedBranch && !valid) {
                        this.selectedBranch = '';
                        window.dispatchEvent(new CustomEvent('clear-selection', {
                            detail: {
                                name: 'branch_id'
                            }
                        }));
                    }
                },

                validateCircle() {
                    const valid = this.circleOptions.find(o => String(o.value) === String(this.selectedCircle));
                    if (this.selectedCircle && !valid) {
                        this.selectedCircle = '';
                        window.dispatchEvent(new CustomEvent('clear-selection', {
                            detail: {
                                name: 'circle_id'
                            }
                        }));
                    }
                },

                updateOptions(name, options) {
                    window.dispatchEvent(new CustomEvent('update-options', {
                        detail: {
                            name,
                            options,
                            preserveSelection: true
                        },
                    }));
                },
            };
        }
    </script>
</x-layouts.markaz-layout>