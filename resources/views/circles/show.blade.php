<x-layouts.markaz-layout>
    <x-slot name="title">{{ $circle->name }}</x-slot>

    {{-- ─── هيدر الحلقة ─── --}}
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden shadow-xl mb-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 relative z-10">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <h1 class="text-3xl font-black">{{ $circle->name }}</h1>
                    @php
                    $levelStyles = match ($circle->level) {
                    'build' => 'bg-blue-400/20 text-blue-100',
                    'mastery' => 'bg-purple-400/20 text-purple-100',
                    'creativity' => 'bg-amber-400/20 text-amber-100',
                    default => 'bg-white/10 text-white/80',
                    };
                    @endphp
                    <span class="px-3 py-1 rounded-full text-xs font-bold {{ $levelStyles }}">
                        {{ match($circle->level) {
                            'build' => 'بناء',
                            'mastery' => 'إتقان',
                            'creativity' => 'إبداع',
                            default => $circle->level,
                        } }}
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-4 text-emerald-100/80 text-sm">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        {{ $circle->center?->name ?? '—' }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                        </svg>
                        {{ $circle->students?->count() ?? 0 }} طالب
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ $circle->groupSessionPlans?->count() ?? 0 }} حصة
                    </span>
                </div>
            </div>

            {{-- أزرار التحكم --}}
            <div class="flex flex-wrap items-center gap-3">
                @can('edit circles')
                <a href="{{ route('circles.edit', $circle) }}"
                    class="px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white font-bold rounded-xl flex items-center gap-2 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    تعديل الحلقة
                </a>
                @endcan
                <a href="{{ route('circles.index') }}"
                    class="px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white font-bold rounded-xl flex items-center gap-2 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    رجوع
                </a>
            </div>
        </div>
        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
    </div>

    {{-- ─── تبويبات التنقل ─── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6" id="circle-tabs">
        <div class="flex border-b border-gray-100 overflow-x-auto">
            <button onclick="switchTab('info')" id="tab-btn-info"
                class="tab-btn px-6 py-4 text-sm font-bold whitespace-nowrap transition-colors flex items-center gap-2 border-b-2 border-[#0a5c36] text-[#0a5c36]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                معلومات الحلقة
            </button>

            @if($circle->type === 'group')
            <button onclick="switchTab('students')" id="tab-btn-students"
                class="tab-btn px-6 py-4 text-sm font-bold whitespace-nowrap transition-colors flex items-center gap-2 border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                </svg>
                تفاصيل البناء ({{ $circle->studentConstructionDetails?->count() ?? 0 }})
            </button>
            <button onclick="switchTab('sessions')" id="tab-btn-sessions"
                class="tab-btn px-6 py-4 text-sm font-bold whitespace-nowrap transition-colors flex items-center gap-2 border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                الجلسات ({{ $circle->groupSessionPlans?->count() ?? 0 }})
            </button>
            @endif
        </div>

        @if($circle->type === 'group')
        {{-- ─── تبويب تفاصيل البناء ─── --}}
        <div id="tab-students" class="tab-content p-6" style="display: none;">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-800">خطة البناء</h3>

                @php
                $groupPlan = $circle->studentConstructionDetails?->firstWhere('student_id', null);
                @endphp

                @if($groupPlan)
                <div class="flex items-center gap-2">
                    {{-- تعديل خطة الحلقة الجماعية --}}
                    @can('edit students')
                    <a href="{{ route('student-construction-details.edit', $groupPlan) }}"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-lg flex items-center gap-2 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        تعديل خطة الحلقة
                    </a>
                    @endcan

                    {{-- حذف خطة الحلقة الجماعية --}}
                    @can('delete students')
                    <form method="POST" action="{{ route('student-construction-details.destroy', $groupPlan) }}"
                        onsubmit="confirmDelete(event, { name: 'خطة الحلقة', type: 'الخطة' })"
                        class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-bold rounded-lg flex items-center gap-2 transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            حذف
                        </button>
                    </form>
                    @endcan
                </div>
                @else
                {{-- إضافة خطة للحلقة الجماعية --}}
                @can('create students')
                <a href="{{ route('student-construction-details.create', ['circle_id' => $circle->id]) }}"
                    class="px-4 py-2 bg-[#0a5c36] hover:bg-[#08492a] text-white text-sm font-bold rounded-lg flex items-center gap-2 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    إضافة خطة الحلقة
                </a>
                @endcan
                @endif
            </div>

            @if($groupPlan)
            {{-- عرض خطة الحلقة الجماعية --}}
            <div class="bg-white border border-gray-100 rounded-xl overflow-hidden">
                {{-- الهيدر --}}
                <div class="bg-blue-50 px-5 py-3 border-b border-blue-100 flex items-center gap-2">
                    <span class="w-2 h-2 bg-blue-500 rounded-full"></span>
                    <span class="text-sm font-bold text-blue-800">خطة جماعية — جميع الطلاب يتبعون هذه الخطة</span>
                </div>

                <div class="p-5">
                    {{-- السورة الحالية --}}
                    @if($groupPlan->currentSurah)
                    <div class="flex items-center gap-3 mb-5 p-3 bg-emerald-50 rounded-lg">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        <div>
                            <p class="text-xs text-emerald-600 font-medium">السورة الحالية</p>
                            <p class="text-sm font-bold text-gray-800">{{ $groupPlan->currentSurah->name_arabic }}</p>
                        </div>
                    </div>
                    @endif

                    {{-- الخطط --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
                        {{-- الحفظ الجديد --}}
                        <div class="bg-gray-50 rounded-xl p-4 text-center">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                            </div>
                            <p class="text-xs text-gray-400 mb-1">خطة الحفظ الجديد</p>
                            <p class="text-lg font-bold text-gray-800">{{ $groupPlan->new_memorization_plan }}</p>
                        </div>

                        {{-- المراجعة --}}
                        <div class="bg-gray-50 rounded-xl p-4 text-center">
                            <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </div>
                            <p class="text-xs text-gray-400 mb-1">خطة المراجعة</p>
                            <p class="text-lg font-bold text-gray-800">{{ $groupPlan->revision_plan }}</p>
                        </div>

                        {{-- الحفظ القديم --}}
                        <div class="bg-gray-50 rounded-xl p-4 text-center">
                            <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <p class="text-xs text-gray-400 mb-1">خطة الحفظ القديم</p>
                            <p class="text-lg font-bold text-gray-800">{{ $groupPlan->old_memorization_plan }}</p>
                        </div>
                    </div>

                    {{-- تقييم التسكين --}}
                    @if($groupPlan->placement_evaluation)
                    <div class="bg-gray-50 rounded-xl p-4">
                        <p class="text-xs font-bold text-gray-400 mb-2 flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            تقييم التسكين
                        </p>
                        <p class="text-sm text-gray-700 leading-relaxed">{{ $groupPlan->placement_evaluation }}</p>
                    </div>
                    @endif

                    {{-- معلومات إضافية --}}
                    <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-400">
                        <span>نظام الدراسة: <span class="font-medium text-gray-600">{{ $groupPlan->study_system === 'group' ? 'جماعي' : 'فردي' }}</span></span>
                        <span>آخر تحديث: {{ $groupPlan->updated_at?->format('Y-m-d') ?? '—' }}</span>
                    </div>
                </div>
            </div>
            @else
            {{-- لا توجد خطة --}}
            <div class="text-center py-12 text-gray-400 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="mb-1">لا توجد خطة بناء مسجلة لهذه الحلقة</p>
                <p class="text-sm">اضغط على "إضافة خطة الحلقة" لإنشاء خطة جديدة</p>
            </div>
            @endif
        </div>
        @endif

        @if($circle->type === 'group')
        {{-- ─── تبويب الجلسات ─── --}}
        <div id="tab-sessions" class="tab-content p-6" style="display: none;">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-800">جلسات الحلقة</h3>
                @can('create group session plans')
                <a href="{{ route('group-session-plans.create', ['circle_id' => $circle->id]) }}"
                    class="px-4 py-2 bg-[#0a5c36] hover:bg-[#08492a] text-white text-sm font-bold rounded-lg flex items-center gap-2 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    إضافة حصة
                </a>
                @endcan
            </div>

            @if($circle->groupSessionPlans?->count())
            <div class="space-y-4">
                @foreach($circle->groupSessionPlans as $session)
                <div class="border border-gray-100 rounded-xl p-5 hover:shadow-md transition-shadow bg-white">
                    {{-- الصف العلوي: اسم الحصة + التاريخ + الأزرار --}}
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-[#0a5c36]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 text-base">{{ $session->session_name }}</h4>
                                <span class="text-xs text-gray-400">
                                    {{ $session->created_at->format('Y-m-d') }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @can('edit group session plans')
                            <a href="{{ route('group-session-plans.edit', $session) }}"
                                class="px-3 py-1.5 text-sm font-medium text-blue-600 hover:bg-blue-50 rounded-lg transition flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                تعديل
                            </a>
                            @endcan

                            @can('delete group session plans')
                            <form method="POST" action="{{ route('group-session-plans.destroy', $session) }}"
                                onsubmit="confirmDelete(event, { name: @js($session->session_name), type: @js('الحصة') })"
                                class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 rounded-lg transition flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    حذف
                                </button>
                            </form>
                            @endcan
                        </div>
                    </div>

                    {{-- التفاصيل المرئية دائماً --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        {{-- الوقت --}}
                        <div class="bg-gray-50 rounded-lg p-3 flex items-center gap-3">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <p class="text-xs text-gray-400">الوقت</p>
                                <p class="text-sm font-medium text-gray-700">
                                    {{ \Carbon\Carbon::parse($session->start_time)->format('H:i') }}
                                    <span class="text-gray-400 mx-1">-</span>
                                    {{ \Carbon\Carbon::parse($session->end_time)->format('H:i') }}
                                </p>
                            </div>
                        </div>

                        {{-- المدة --}}
                        <div class="bg-gray-50 rounded-lg p-3 flex items-center gap-3">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <p class="text-xs text-gray-400">المدة</p>
                                <p class="text-sm font-medium text-gray-700">
                                    @php
                                    $start = \Carbon\Carbon::parse($session->start_time);
                                    $end = \Carbon\Carbon::parse($session->end_time);
                                    $diff = $start->diff($end);
                                    echo $diff->h > 0 ? $diff->h . ' ساعة ' : '';
                                    echo $diff->i > 0 ? $diff->i . ' دقيقة' : '';
                                    @endphp
                                </p>
                            </div>
                        </div>

                        {{-- الحالة --}}
                        <div class="bg-gray-50 rounded-lg p-3 flex items-center gap-3">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <p class="text-xs text-gray-400">الحالة</p>
                                <p class="text-sm font-medium text-gray-700">
                                    @if($session->completed_content)
                                    <span class="text-green-600">✓ مكتملة</span>
                                    @else
                                    <span class="text-amber-600">○ مخططة</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- زر إظهار/إخفاء التفاصيل --}}
                    <div class="mb-3">
                        <button onclick="toggleSession({{ $session->id }})"
                            id="toggle-btn-{{ $session->id }}"
                            class="text-sm font-medium text-[#0a5c36] hover:text-[#08492a] flex items-center gap-1.5 transition-colors">
                            <svg id="icon-show-{{ $session->id }}" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                            <svg id="icon-hide-{{ $session->id }}" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                            </svg>
                            <span id="text-toggle-{{ $session->id }}">عرض التفاصيل</span>
                        </button>
                    </div>

                    {{-- محتوى قابل للإخفاء --}}
                    <div id="session-details-{{ $session->id }}" class="hidden">
                        {{-- المحتوى المخطط --}}
                        @if($session->planned_content)
                        <div class="mb-3">
                            <p class="text-xs font-bold text-gray-400 mb-1.5">الخطة المقترحة</p>
                            <p class="text-sm text-gray-600 bg-blue-50/50 rounded-lg p-3 leading-relaxed">
                                {{ $session->planned_content }}
                            </p>
                        </div>
                        @endif

                        {{-- المحتوى المنجز --}}
                        @if($session->completed_content)
                        <div class="mb-3">
                            <p class="text-xs font-bold text-gray-400 mb-1.5">ما تم إنجازه</p>
                            <p class="text-sm text-gray-600 bg-green-50/50 rounded-lg p-3 leading-relaxed">
                                {{ $session->completed_content }}
                            </p>
                        </div>
                        @endif

                        {{-- الملاحظات --}}
                        @if($session->notes)
                        <div>
                            <p class="text-xs font-bold text-gray-400 mb-1.5">ملاحظات</p>
                            <p class="text-sm text-gray-500 bg-gray-50 rounded-lg p-3 leading-relaxed">
                                {{ $session->notes }}
                            </p>
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-12 text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p>لا توجد جلسات مسجلة</p>
            </div>
            @endif
        </div>
        @endif

        {{-- ─── تبويب معلومات الحلقة ─── --}}
        <div id="tab-info" class="tab-content p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div class="bg-gray-50 rounded-xl p-4">
                        <label class="block text-xs font-bold text-gray-400 mb-1">المعلم الرئيسي</label>
                        <p class="text-sm font-medium text-gray-800">{{ $circle->mainTeachers->first()?->name ?? '—' }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4">
                        <label class="block text-xs font-bold text-gray-400 mb-1">المعلم المساعد</label>
                        <p class="text-sm font-medium text-gray-800">{{ $circle->assistantTeachers->first()?->name ?? '—' }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4">
                        <label class="block text-xs font-bold text-gray-400 mb-1">المشرفون</label>
                        <p class="text-sm font-medium text-gray-800">{{ $circle->supervisors->pluck('name')->join('، ') ?: '—' }}</p>
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="bg-gray-50 rounded-xl p-4">
                        <label class="block text-xs font-bold text-gray-400 mb-1">النوع</label>
                        <p class="text-sm font-medium text-gray-800">{{ $circle->type_arabic }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4">
                        <label class="block text-xs font-bold text-gray-400 mb-1">الفرع</label>
                        <p class="text-sm font-medium text-gray-800">{{ $circle->center?->name ?? '—' }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4">
                        <label class="block text-xs font-bold text-gray-400 mb-1">عدد الطلاب</label>
                        <p class="text-sm font-medium text-gray-800">{{ $circle->students?->count() ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            switchTab('info');
        });

        function switchTab(tabName) {
            // إخفاء كل المحتويات
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            // إظهار المحتوى المطلوب
            const tabContent = document.getElementById('tab-' + tabName);
            if (tabContent) {
                tabContent.style.display = 'block';
            }

            // إعادة تعيين الأزرار
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('border-[#0a5c36]', 'text-[#0a5c36]');
                btn.classList.add('border-transparent', 'text-gray-500');
            });

            // تفعيل الزر النشط
            const activeBtn = document.getElementById('tab-btn-' + tabName);
            if (activeBtn) {
                activeBtn.classList.remove('border-transparent', 'text-gray-500');
                activeBtn.classList.add('border-[#0a5c36]', 'text-[#0a5c36]');
            }
        }

        function toggleSession(sessionId) {
            const details = document.getElementById('session-details-' + sessionId);
            const iconShow = document.getElementById('icon-show-' + sessionId);
            const iconHide = document.getElementById('icon-hide-' + sessionId);
            const text = document.getElementById('text-toggle-' + sessionId);

            if (details.classList.contains('hidden')) {
                // إظهار
                details.classList.remove('hidden');
                iconShow.classList.add('hidden');
                iconHide.classList.remove('hidden');
                text.textContent = 'إخفاء التفاصيل';
            } else {
                // إخفاء
                details.classList.add('hidden');
                iconShow.classList.remove('hidden');
                iconHide.classList.add('hidden');
                text.textContent = 'عرض التفاصيل';
            }
        }
    </script>
</x-layouts.markaz-layout>