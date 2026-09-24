<x-layouts.markaz-layout>
    <div class="space-y-6"
        x-data="unpaidNotify()"
        x-init="init()">
        <script>
            window.__csrf = '{{ csrf_token() }}';
            window.__notifyUnpaidUrlBase = '{{ route("subscriptions.notify-unpaid", ":id") }}';
        </script>

        {{-- ─── Header ────────────────────────────────────────────── --}}
        <div class="bg-[#0b3d2c] rounded-3xl p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6">
            <div class="text-right w-full md:w-auto z-10 wrap-break-word">
                <h1 class="text-3xl font-black mb-2">الطلاب المتعثرين</h1>
                <p class="text-emerald-100/80 text-sm font-medium">قائمة بالطلاب المتأخرين عن سداد الاشتراكات</p>
            </div>
            <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                <a href="{{ route('subscriptions.index') }}"
                    class="w-full md:w-auto px-6 py-3 bg-white/10 hover:bg-white/20 border border-white/10 text-white font-bold rounded-2xl flex items-center justify-center gap-2 transition-all active:scale-95">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    عودة لإدارة الاشتراكات
                </a>
            </div>
            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
        </div>

        {{-- ─── Filters ───────────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 mb-6"
            x-data="lateUnpaidFilters()"
            x-init="init()">

            <form method="GET" action="{{ route('subscriptions.late_and_unpaid') }}" id="filterForm">

                {{-- الصف الأول: البحث + الأزرار --}}
                <div class="flex items-end gap-3 pb-4 mb-4 border-b border-gray-100">
                    <div class="flex-1 min-w-0">
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

                    @if($hasActiveFilters)
                    <a href="{{ route('subscriptions.late_and_unpaid') }}"
                        class="shrink-0 bg-gray-100 text-gray-600 px-5 h-11 rounded-xl font-bold hover:bg-gray-200 transition flex items-center gap-2 text-sm whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 4H15" />
                        </svg>
                        إعادة تعيين
                    </a>
                    @endif

                    <button type="submit"
                        class="shrink-0 bg-[#0b3d2c] text-white px-6 h-11 rounded-xl font-bold hover:bg-[#0a3324] shadow-sm transition flex items-center gap-2 text-sm whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z" />
                        </svg>
                        تطبيق التصفية
                    </button>
                </div>

                {{-- الصف الثاني: الحالة + الفرع + الحلقة + المعلم --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">

                    <div class="w-full">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">الحالة</label>
                        <select name="status"
                            class="w-full rounded-xl border-gray-200 focus:border-[#0b3d2c] focus:ring-[#0b3d2c] text-sm h-11">
                            <option value="">جميع الحالات</option>
                            <option value="مقيد" {{ request('status', $selectedStatus ?? '') === 'مقيد'  ? 'selected' : '' }}>🟢 مقيد</option>
                            <option value="متوقف" {{ request('status', $selectedStatus ?? '') === 'متوقف' ? 'selected' : '' }}>🟠 متوقف</option>
                        </select>
                    </div>

                    @if($centers->count() > 0)
                    <div class="w-full">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">المركز</label>
                        <x-searchable-select
                            name="center_id"
                            placeholder="جميع المراكز"
                            search-placeholder="ابحث عن مركز..."
                            default-option="جميع المراكز"
                            default-value="{{ request('center_id', $selectedCenterId ?? '') }}"
                            :options="$centers->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values()" />
                    </div>
                    @endif

                    @if($branches->count() > 0)
                    <div class="w-full">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">المقر</label>
                        <x-searchable-select
                            name="branch_id"
                            placeholder="جميع المقرات"
                            search-placeholder="ابحث عن مقر..."
                            default-option="جميع المقرات"
                            default-value="{{ request('branch_id', $selectedBranchId ?? '') }}"
                            :options="$branches->map(fn($b) => ['value' => $b->id, 'label' => $b->name])->values()" />
                    </div>
                    @endif

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

                    <div class="w-full">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">المعلم</label>
                        <x-searchable-select
                            name="teacher_id"
                            placeholder="جميع المعلمين"
                            search-placeholder="ابحث عن معلم..."
                            default-option="جميع المعلمين"
                            default-value="{{ request('teacher_id', $selectedTeacherId ?? '') }}"
                            :options="$teachers->map(fn($t) => ['value' => $t->id, 'label' => $t->user->name ?? '—'])->values()" />
                    </div>

                </div>
            </form>
        </div>

        {{-- ─── Statistics Cards ──────────────────────────────────── --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-4 group hover:border-red-100 transition-all">
                <div class="w-14 h-14 bg-red-50 rounded-2xl flex items-center justify-center text-red-600 transition-transform group-hover:scale-110">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-gray-400 text-xs font-bold">عدد الطلاب المتعثرين</p>
                    <h3 class="text-2xl font-black text-gray-800">{{ $totalStudents }}</h3>
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-4 group hover:border-amber-100 transition-all">
                <div class="w-14 h-14 bg-amber-50 rounded-2xl flex items-center justify-center text-amber-600 transition-transform group-hover:scale-110">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-gray-400 text-xs font-bold">إجمالي الأشهر المتأخرة</p>
                    <h3 class="text-2xl font-black text-gray-800">{{ $totalUnpaidMonths }}</h3>
                </div>
            </div>

        </div>

        {{-- ─── Results Table ─────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-800">قائمة الطلاب المتعثرين</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-600">#</th>
                            @php
                            $currentSort = request('sort', 'unpaid_months');
                            $currentDir = request('direction', 'desc');

                            $sortLink = fn(string $col) =>
                            request()->fullUrlWithQuery([
                            'sort' => $col,
                            'direction' => ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc',
                            ]);

                            $sortIcon = function(string $col) use ($currentSort, $currentDir): string {
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

                            {{-- اسم الطالب --}}
                            <th class="{{ $thBase }} {{ $currentSort === 'name' ? $thActive : $thMuted }}"
                                onclick="window.location='{{ $sortLink('name') }}'">
                                <div class="flex items-center justify-end gap-1">
                                    اسم الطالب
                                    {!! $sortIcon('name') !!}
                                </div>
                            </th>

                            {{-- الحالة --}}
                            <th class="{{ $thBase }} {{ $currentSort === 'status' ? $thActive : $thMuted }}"
                                onclick="window.location='{{ $sortLink('status') }}'">
                                <div class="flex items-center justify-end gap-1">
                                    الحالة
                                    {!! $sortIcon('status') !!}
                                </div>
                            </th>

                            {{-- الحلقة --}}
                            <th class="{{ $thBase }} {{ $currentSort === 'circle' ? $thActive : $thMuted }}"
                                onclick="window.location='{{ $sortLink('circle') }}'">
                                <div class="flex items-center justify-end gap-1">
                                    الحلقة
                                    {!! $sortIcon('circle') !!}
                                </div>
                            </th>

                            {{-- المركز --}}
                            <th class="{{ $thBase }} {{ $currentSort === 'center' ? $thActive : $thMuted }}"
                                onclick="window.location='{{ $sortLink('center') }}'">
                                <div class="flex items-center justify-end gap-1">
                                    المركز
                                    {!! $sortIcon('center') !!}
                                </div>
                            </th>

                            {{-- المقر --}}
                            <th class="{{ $thBase }} {{ $currentSort === 'branch' ? $thActive : $thMuted }}"
                                onclick="window.location='{{ $sortLink('branch') }}'">
                                <div class="flex items-center justify-end gap-1">
                                    المقر
                                    {!! $sortIcon('branch') !!}
                                </div>
                            </th>

                            {{-- عدد الأشهر المتأخرة --}}
                            <th class="{{ $thBase }} {{ $currentSort === 'unpaid_months' ? $thActive : $thMuted }}"
                                onclick="window.location='{{ $sortLink('unpaid_months') }}'">
                                <div class="flex items-center justify-end gap-1">
                                    عدد الأشهر المتأخرة
                                    {!! $sortIcon('unpaid_months') !!}
                                </div>
                            </th>

                            {{-- إجراءات - بدون ترتيب --}}
                            <th class="px-6 py-4 text-sm font-semibold text-gray-600">إجراءات</th>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-600 text-center">تنبيه ولي الأمر</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($students as $index => $student)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 text-gray-400 text-sm font-medium">
                                {{ ($students->currentPage() - 1) * $students->perPage() + $index + 1 }}
                            </td>
                            <td class="px-6 py-4 font-bold text-gray-900">{{ $student->name }}</td>
                            <td class="px-6 py-4">
                                @if($student->status === 'مقيد')
                                <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-xs font-bold flex items-center gap-1 w-fit">
                                    <span class="w-2 h-2 bg-emerald-500 rounded-full"></span>
                                    مقيد
                                </span>
                                @elseif($student->status === 'متوقف')
                                <span class="bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-xs font-bold flex items-center gap-1 w-fit">
                                    <span class="w-2 h-2 bg-orange-500 rounded-full"></span>
                                    متوقف
                                </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ $student->circle?->name ?? '—' }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ $student->circle?->branch?->center?->name ?? '—' }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ $student->circle?->branch?->name ?? '—' }}</td>
                            <td class="px-6 py-4">
                                <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-sm font-bold">
                                    {{ $student->unpaid_months_count }} أشهر
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <a href="{{ route('subscriptions.details_unpaid', $student->id) }}"
                                    class="text-blue-600 hover:text-blue-800 font-medium text-sm  px-3 py-1 rounded-lg hover:bg-blue-50 transition">
                                    عرض التفاصيل
                                </a>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button @click="openNotifyModal({{ $student->id }}, '{{ addslashes($student->name) }}', {{ $student->unpaid_months_count }})"
                                        :disabled="sendingIds.includes({{ $student->id }})"
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all"
                                        :class="sendingIds.includes({{ $student->id }}) ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-[#0b3d2c] hover:bg-[#0a3324] text-white'">
                                        <span x-show="!sendingIds.includes({{ $student->id }})">إرسال</span>
                                        <span x-show="sendingIds.includes({{ $student->id }})" class="inline-flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                            </svg>
                                            جاري
                                        </span>
                                    </button>
                                    @if($student->whatsapp_number)
                                    <button @click="openWhatsApp('{{ addslashes($student->whatsapp_number) }}', '{{ addslashes($student->name) }}', {{ $student->unpaid_months_count }})"
                                        title="فتح واتساب"
                                        class="p-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white transition-all">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
                                            <path d="M12.004 2.003c-5.514 0-9.997 4.483-9.997 9.997 0 1.762.462 3.484 1.34 5.003l-1.42 5.19 5.312-1.394a9.96 9.96 0 004.765 1.213h.005c5.514 0 9.997-4.483 9.997-9.997 0-2.669-1.04-5.178-2.927-7.065a9.935 9.935 0 00-7.075-2.947zm5.836 15.798a8.298 8.298 0 01-4.836 1.567h-.004a8.29 8.29 0 01-4.223-1.155l-.303-.18-3.15.827.842-3.074-.198-.316a8.267 8.267 0 01-1.267-4.416c0-4.591 3.735-8.325 8.328-8.325 2.225 0 4.316.867 5.89 2.443a8.263 8.263 0 012.437 5.885c0 4.592-3.735 8.326-8.316 8.326-.001 0-.001 0 0 0z" />
                                        </svg>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-gray-400">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <p class="text-lg font-medium">جميع الطلاب ملتزمون بالسداد!</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <x-pagination :paginator="$students" />

        <!-- Notify Modal -->
        <div x-show="showModal"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
            @click.self="closeModal()">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 p-6"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-800">إرسال تنبيه</h3>
                    <button @click="closeModal()" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <p class="text-sm text-gray-500 mb-4">
                    كتابة رسالة تنبيه لولي أمر الطالب: <span class="font-medium text-gray-700" x-text="selectedStudentName"></span>
                </p>
                <textarea x-model="customMessage"
                    class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-700 resize-none focus:outline-none focus:ring-2 focus:ring-[#0b3d2c]/20 focus:border-[#0b3d2c] transition"
                    rows="4"
                    placeholder="اكتب رسالة التنبيه..."></textarea>
                <div class="flex justify-end gap-3 mt-4">
                    <button @click="closeModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition">
                        إلغاء
                    </button>
                    <button @click="confirmNotify()"
                        :disabled="sendingIds.includes(selectedStudentId)"
                        class="px-5 py-2 text-sm font-bold text-white bg-[#0b3d2c] hover:bg-[#0a3324] rounded-xl transition disabled:opacity-50 disabled:cursor-not-allowed">
                        إرسال التنبيه
                    </button>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script>
        let searchTimeout;

        function debounceSearch(input) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => input.form.submit(), 500);
        }

        function unpaidNotify() {
            return {
                csrfToken: window.__csrf,
                showModal: false,
                selectedStudentId: null,
                selectedStudentName: '',
                customMessage: '',
                sendingIds: [],

                init() {},

                openNotifyModal(studentId, studentName, unpaidCount) {
                    this.selectedStudentId = studentId;
                    this.selectedStudentName = studentName;
                    this.customMessage = 'نود تذكيركم بوجود ' + unpaidCount + ' أشهر متأخرة السداد لابنكم ' + studentName + '. يرجى التواصل مع الإدارة لتسوية الموقف.';
                    this.showModal = true;
                },

                closeModal() {
                    this.showModal = false;
                    this.selectedStudentId = null;
                    this.selectedStudentName = '';
                    this.customMessage = '';
                },

                async confirmNotify() {
                    const studentId = this.selectedStudentId;
                    if (!studentId || this.sendingIds.includes(studentId)) return;
                    this.sendingIds.push(studentId);
                    this.closeModal();

                    try {
                        const url = window.__notifyUnpaidUrlBase.replace(':id', studentId);

                        // ✅ تأكد من وجود CSRF token
                        if (!this.csrfToken) {
                            showError('رمز الأمان غير موجود. يرجى تحديث الصفحة.', 'خطأ');
                            return;
                        }

                        const res = await fetch(url, {
                            method: 'POST', // ✅ تأكد من POST
                            headers: {
                                'X-CSRF-TOKEN': this.csrfToken,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest' // ✅ أضف هذا
                            },
                            body: JSON.stringify({
                                message: this.customMessage
                            }),
                        });

                        const contentType = res.headers.get('content-type');
                        let data = {};

                        if (contentType && contentType.includes('application/json')) {
                            data = await res.json();
                        } else {
                            const text = await res.text();
                            console.error('Non-JSON response:', text.substring(0, 500));
                            showError('حدث خطأ في الخادم.', 'خطأ');
                            return;
                        }

                        if (res.ok) {
                            showSuccess(data.message || 'تم الإرسال بنجاح', 'تم الإرسال');
                        } else {
                            showError(data.message || 'حدث خطأ أثناء الإرسال.', 'خطأ');
                        }
                    } catch (err) {
                        console.error('Fetch error:', err);
                        showError('حدث خطأ في الاتصال.', 'خطأ');
                    } finally {
                        this.sendingIds = this.sendingIds.filter(id => id !== studentId);
                    }
                },

                normalizeWhatsAppNumber(raw) {
                    let digits = String(raw).replace(/\D/g, '');
                    if (!digits) return null;
                    if (digits.startsWith('020')) {
                        digits = digits.substring(1);
                    } else if (digits.startsWith('0')) {
                        digits = '20' + digits.substring(1);
                    } else if (!digits.startsWith('20')) {
                        digits = '20' + digits;
                    }
                    return (digits.length >= 10 && digits.length <= 15) ? digits : null;
                },

                openWhatsApp(rawNumber, studentName, unpaidCount) {
                    const normalized = this.normalizeWhatsAppNumber(rawNumber);
                    if (!normalized) {
                        showError('رقم الواتساب غير صالح.', 'خطأ');
                        return;
                    }
                    const message = 'نود تذكيركم بوجود ' + unpaidCount + ' أشهر متأخرة السداد لابنكم ' + studentName + '. يرجى التواصل مع الإدارة لتسوية الموقف.';
                    const url = 'https://wa.me/' + normalized + '?text=' + encodeURIComponent(message);
                    window.open(url, '_blank');
                },
            }
        }

        function lateUnpaidFilters() {
            return {
                selectedCenter: '{{ request("center_id", $selectedCenterId ?? "") }}',
                selectedBranch: '{{ request("branch_id", $selectedBranchId ?? "") }}',
                selectedCircle: '{{ request("circle_id", $selectedCircleId ?? "all") }}',
                selectedTeacher: '{{ request("teacher_id", $selectedTeacherId ?? "") }}',

                centers: @json($centers),
                branches: @json($branches),
                circles: @json($circles),
                teachers: @json($teachers),

                filterUrl: '{{ route("subscriptions.filter-options") }}',

                init() {
                    window.addEventListener('searchable-change', (e) => {
                        if (e.detail.name === 'center_id') {
                            this.selectedCenter = e.detail.value || '';
                            this.onCenterChange();
                        }
                        if (e.detail.name === 'branch_id') {
                            this.selectedBranch = e.detail.value || '';
                            this.onBranchChange();
                        }
                        if (e.detail.name === 'circle_id') {
                            this.selectedCircle = e.detail.value || 'all';
                            this.onCircleChange();
                        }
                        if (e.detail.name === 'teacher_id') {
                            this.selectedTeacher = e.detail.value;
                            this.onTeacherChange();
                        }
                    });

                    if (this.selectedCenter || this.selectedBranch || (this.selectedCircle && this.selectedCircle !== 'all') || this.selectedTeacher) {
                        this.fetchOptions();
                    }
                },

                async fetchOptions() {
                    const params = new URLSearchParams();
                    if (this.selectedCenter) params.append('center_id', this.selectedCenter);
                    if (this.selectedBranch) params.append('branch_id', this.selectedBranch);
                    if (this.selectedCircle && this.selectedCircle !== 'all') params.append('circle_id', this.selectedCircle);
                    if (this.selectedTeacher) params.append('teacher_id', this.selectedTeacher);

                    try {
                        const res = await fetch(`${this.filterUrl}?${params}`);
                        const data = await res.json();

                        this.centers = data.centers;
                        this.branches = data.branches;
                        this.circles = data.circles;
                        this.teachers = data.teachers;

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

                        if (this.selectedCenter && !data.centers.find(c => c.id == this.selectedCenter)) this.selectedCenter = '';
                        if (this.selectedBranch && !data.branches.find(b => b.id == this.selectedBranch)) this.selectedBranch = '';
                        if (this.selectedCircle !== 'all' && !data.circles.find(c => c.id == this.selectedCircle)) this.selectedCircle = 'all';
                        if (this.selectedTeacher && !data.teachers.find(t => t.id == this.selectedTeacher)) this.selectedTeacher = '';
                    } catch (e) {
                        console.error('Filter fetch error:', e);
                    }
                },

                onCenterChange() {
                    this.selectedBranch = '';
                    this.selectedCircle = 'all';
                    this.selectedTeacher = '';
                    this.fetchOptions();
                },
                onBranchChange() {
                    this.selectedCircle = 'all';
                    this.selectedTeacher = '';
                    this.fetchOptions();
                },
                onCircleChange() {
                    this.selectedTeacher = '';
                    this.fetchOptions();
                },
                onTeacherChange() {
                    this.selectedCircle = 'all';
                    this.fetchOptions();
                },
            }
        }
    </script>
</x-layouts.markaz-layout>