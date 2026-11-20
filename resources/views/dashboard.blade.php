<x-layouts.markaz-layout>
@php
$isAdmin = Auth::user()->hasAnyRole(['admin', 'supervisor']);
$isTeacher = Auth::user()->hasRole('teacher');
$isGuardian = Auth::user()->hasRole('guardian');
@endphp

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- HEADER + FILTER                                           --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="bg-gradient-to-br from-[#0b3d2c] via-[#0d4d35] to-[#0b3d2c] rounded-3xl p-8 text-white relative overflow-hidden shadow-2xl mb-6">
    <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/4"></div>
    <div class="absolute bottom-0 left-0 w-48 h-48 bg-white/5 rounded-full blur-2xl translate-y-1/2 -translate-x-1/4"></div>

    <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
        <div class="flex items-center gap-5">
            <div class="w-16 h-16 bg-white/15 backdrop-blur-sm rounded-2xl flex items-center justify-center border border-white/20 shadow-lg">
                <svg class="w-8 h-8 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
            </div>
            <div>
                <h1 class="text-3xl font-black tracking-tight mb-2">مرحباً، {{ Auth::user()->name }}</h1>
                <p class="text-emerald-100/70 text-sm flex items-center gap-2">
                    <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse"></span>
                    @if ($isAdmin)
                    لوحة التحكم الرئيسية — لديك الصلاحيات الكاملة
                    @elseif($isTeacher)
                    لوحة المعلم — متابعة حلقاتك وطلابك
                    @else
                    بوابة المركز — متابعة أبنائك
                    @endif
                </p>
            </div>
        </div>
        <div class="bg-white/10 backdrop-blur-sm border border-white/15 px-5 py-2.5 rounded-2xl font-semibold text-sm flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            {{ now()->locale('ar')->isoFormat('dddd، D MMMM YYYY') }}
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- CENTER FILTER BAR                                         --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
@if($isAdmin && $centers->count() > 0)
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-bold text-gray-500 mb-1.5">تصفية حسب الفرع</label>
            <div class="relative">
                <select name="center_id" onchange="this.form.submit()"
                    class="w-full p-2.5 pr-10 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0a5c36] focus:border-[#0a5c36] transition-all appearance-none bg-white cursor-pointer">
                    <option value="">📍 جميع الفروع</option>
                    @foreach($centers as $center)
                    <option value="{{ $center->id }}" {{ $selectedCenterId == $center->id ? 'selected' : '' }}>
                        {{ $center->name }}
                    </option>
                    @endforeach
                </select>
                <svg class="w-4 h-4 absolute left-3 top-3 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </div>

        @if($selectedCenterId)
        <a href="{{ route('dashboard') }}"
            class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold rounded-xl text-sm transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            إلغاء الفلتر
        </a>
        @endif

        <div class="text-xs text-gray-400 font-medium flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            @if($selectedCenterId)
            يتم عرض بيانات: <span class="font-bold text-gray-600">{{ $centers->firstWhere('id', $selectedCenterId)?->name }}</span>
            @else
            يتم عرض بيانات جميع الفروع
            @endif
        </div>
    </form>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- STATS CARDS                                               --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    {{-- ... نفس البطاقات السابقة ... --}}
    @if ($isAdmin)
    {{-- Card 1: Attendance --}}
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group relative overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 opacity-40 group-hover:opacity-70 transition-opacity"></div>
        <div class="relative z-10">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <span class="text-xs font-bold text-blue-500 bg-blue-50 px-2.5 py-1 rounded-full border border-blue-100">اخر ٣٠ يوم</span>
            </div>
            <div class="flex items-end gap-2 mb-1">
                <h3 class="text-3xl font-black text-gray-800 tracking-tight">{{ $stats['attendance_rate'] ?? '0' }}%</h3>
                <svg class="w-5 h-5 text-blue-400 mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
            </div>
            <p class="text-xs text-gray-400 font-medium">نسبة الحضور</p>
            <div class="mt-4 w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-blue-400 to-blue-600 rounded-full transition-all duration-1000" style="width: {{ min($stats['attendance_rate'] ?? 0, 100) }}%"></div>
            </div>
        </div>
    </div>

    {{-- Card 2: Revenue --}}
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group relative overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-50 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 opacity-40 group-hover:opacity-70 transition-opacity"></div>
        <div class="relative z-10">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                </div>
                @if(($stats['revenue_growth'] ?? 0) >= 0)
                <span class="text-xs font-bold text-emerald-500 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-100 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                    </svg>
                    {{ $stats['revenue_growth'] ?? 0 }}%
                </span>
                @else
                <span class="text-xs font-bold text-red-500 bg-red-50 px-2.5 py-1 rounded-full border border-red-100 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                    </svg>
                    {{ abs($stats['revenue_growth'] ?? 0) }}%
                </span>
                @endif
            </div>
            <div class="flex items-end gap-2 mb-1">
                <h3 class="text-3xl font-black text-gray-800 tracking-tight">{{ number_format($stats['monthly_revenue'] ?? 0) }}</h3>
                <span class="text-sm text-gray-400 font-bold mb-1.5">ج.م</span>
            </div>
            <p class="text-xs text-gray-400 font-medium">التحصيل هذا الشهر</p>
        </div>
    </div>

    {{-- Card 3: Circles --}}
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group relative overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-amber-50 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 opacity-40 group-hover:opacity-70 transition-opacity"></div>
        <div class="relative z-10">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                </div>
            </div>
            <div class="flex items-end gap-2 mb-1">
                <h3 class="text-3xl font-black text-gray-800 tracking-tight">{{ $stats['circles_count'] ?? 0 }}</h3>
            </div>
            <p class="text-xs text-gray-400 font-medium">حلقة نشطة</p>
        </div>
    </div>

    {{-- Card 4: Students --}}
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group relative overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-teal-50 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 opacity-40 group-hover:opacity-70 transition-opacity"></div>
        <div class="relative z-10">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-teal-50 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <span class="text-xs font-bold text-teal-500 bg-teal-50 px-2.5 py-1 rounded-full border border-teal-100 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    {{ $stats['teachers_count'] ?? 0 }}
                </span>
            </div>
            <div class="flex items-end gap-2 mb-1">
                <h3 class="text-3xl font-black text-gray-800 tracking-tight">{{ $stats['students_count'] ?? 0 }}</h3>
            </div>
            <p class="text-xs text-gray-400 font-medium">طالب مسجل</p>
        </div>
    </div>
    @endif
</div>

@if ($isAdmin)
{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- CHARTS SECTION                                            --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

    {{-- Line Chart: Student Growth (جدد + متوقفين) --}}
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow duration-300">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800">نمو الطلاب</h3>
                    <p class="text-xs text-gray-400 mt-0.5">الطلاب الجدد مقابل المتوقفين — آخر ٦ أشهر</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="flex items-center gap-1 text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-lg border border-emerald-100">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    جدد
                </span>
                <span class="flex items-center gap-1 text-xs font-bold text-red-600 bg-red-50 px-2 py-1 rounded-lg border border-red-100">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span>
                    متوقفين
                </span>
            </div>
        </div>
        <div class="relative" style="height: 280px;">
            <canvas id="growthChart" style="width: 100%; height: 100%;"></canvas>
        </div>
    </div>

    {{-- Doughnut Chart: Status Distribution --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col hover:shadow-md transition-shadow duration-300">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-800">حالات الطلاب</h3>
                <p class="text-xs text-gray-400 mt-0.5">توزيع حسب الحالة</p>
            </div>
        </div>
        <div class="flex-1 relative" style="height: 220px;">
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="relative" style="width: 200px; height: 200px;">
                    <canvas id="statusChart" style="width: 100%; height: 100%;"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-3xl font-black text-gray-800">{{ $stats['students_count'] ?? 0 }}</span>
                        <span class="text-xs text-gray-400 font-medium">طالب</span>
                    </div>
                </div>
            </div>
        </div>
        <div id="statusLegend" class="flex flex-wrap justify-center gap-2 mt-4"></div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- ALERTS SECTION                                            --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

    {{-- Absent Students --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-300">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-red-50/50 to-transparent">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-gray-800">الغياب المتكرر</h3>
                    <p class="text-xs text-gray-400">طلاب بغياب متسلسل</p>
                </div>
            </div>
            <a href="{{ route('attendance.sequential-absences') }}" class="text-xs font-bold text-blue-500 hover:text-blue-700 transition flex items-center gap-1 hover:gap-2 duration-200">
                عرض الكل
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </a>
        </div>
        <div class="divide-y divide-gray-50">
            @forelse($absentStudents ?? [] as $student)
            <div class="flex items-center justify-between p-4 hover:bg-red-50/30 transition-colors group cursor-pointer">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center text-xs font-bold text-red-600 group-hover:scale-110 transition-transform">
                        {{ strtoupper(substr($student->name, 0, 2)) }}
                    </div>
                    <div>
                        <p class="font-bold text-gray-800 text-sm">{{ $student->name }}</p>
                        <p class="text-xs text-gray-400">{{ $student->circle?->name ?? 'بدون حلقة' }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="bg-red-50 text-red-600 px-2.5 py-1 rounded-lg text-xs font-bold border border-red-100">
                        {{ $student->absence_days }} أيام
                    </span>
                    <div class="w-1.5 h-1.5 rounded-full bg-red-400 animate-pulse"></div>
                </div>
            </div>
            @empty
            <div class="p-10 text-center">
                <div class="w-20 h-20 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="text-sm text-gray-400 font-medium">لا يوجد غياب متكرر</p>
                <p class="text-xs text-gray-300 mt-1">جميع الطلاب منتظمون</p>
            </div>
            @endforelse
        </div>
    </div>

    {{-- Unpaid Students --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-300">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-orange-50/50 to-transparent">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-orange-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-gray-800">المتأخرات المالية</h3>
                    <p class="text-xs text-gray-400">طلاب بأشهر غير مسددة</p>
                </div>
            </div>
            <a href="{{ route('subscriptions.late_and_unpaid') }}" class="text-xs font-bold text-blue-500 hover:text-blue-700 transition flex items-center gap-1 hover:gap-2 duration-200">
                التقرير المفصل
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </a>
        </div>
        <div class="divide-y divide-gray-50">
            @forelse($unpaidStudents ?? [] as $student)
            <div class="flex items-center justify-between p-4 hover:bg-orange-50/30 transition-colors group cursor-pointer">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center text-xs font-bold text-orange-600 group-hover:scale-110 transition-transform">
                        {{ strtoupper(substr($student->name, 0, 2)) }}
                    </div>
                    <div>
                        <p class="font-bold text-gray-800 text-sm">{{ $student->name }}</p>
                        <p class="text-xs text-gray-400">{{ $student->circle?->name ?? 'بدون حلقة' }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="bg-orange-50 text-orange-600 px-2.5 py-1 rounded-lg text-xs font-bold border border-orange-100">
                        {{ $student->unpaid_months_count }} شهر
                    </span>
                    <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            @empty
            <div class="p-10 text-center">
                <div class="w-20 h-20 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="text-sm text-gray-400 font-medium">جميع الاشتراكات مسددة</p>
                <p class="text-xs text-gray-300 mt-1">لا يوجد متأخرات مالية</p>
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- QUICK ACCESS                                              --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="bg-gradient-to-br from-white to-gray-50/50 rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow duration-300">
    <div class="flex items-center gap-3 mb-5">
        <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
        </div>
        <div>
            <h3 class="text-lg font-bold text-gray-800">الوصول السريع</h3>
            <p class="text-xs text-gray-400">اختصارات للأقسام الرئيسية</p>
        </div>
    </div>
    <div class="flex flex-wrap gap-3">
        @if ($isAdmin)
        <a href="{{ route('students.index') }}" class="group px-5 py-3 bg-white hover:bg-teal-50 text-teal-700 rounded-xl font-bold text-sm transition-all duration-300 flex items-center gap-3 border border-gray-100 hover:border-teal-200 hover:shadow-md hover:-translate-y-0.5">
            <div class="w-10 h-10 bg-teal-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
            <div class="text-right">
                <span class="block">الطلاب</span>
                <span class="text-xs text-teal-400 font-normal">إدارة الطلاب</span>
            </div>
        </a>
        <a href="{{ route('circles.index') }}" class="group px-5 py-3 bg-white hover:bg-amber-50 text-amber-700 rounded-xl font-bold text-sm transition-all duration-300 flex items-center gap-3 border border-gray-100 hover:border-amber-200 hover:shadow-md hover:-translate-y-0.5">
            <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            </div>
            <div class="text-right">
                <span class="block">الحلقات</span>
                <span class="text-xs text-amber-400 font-normal">إدارة الحلقات</span>
            </div>
        </a>
        <a href="{{ route('attendance.index') }}" class="group px-5 py-3 bg-white hover:bg-blue-50 text-blue-700 rounded-xl font-bold text-sm transition-all duration-300 flex items-center gap-3 border border-gray-100 hover:border-blue-200 hover:shadow-md hover:-translate-y-0.5">
            <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <div class="text-right">
                <span class="block">الحضور</span>
                <span class="text-xs text-blue-400 font-normal">تسجيل الحضور</span>
            </div>
        </a>
        <a href="{{ route('teachers.index') }}" class="group px-5 py-3 bg-white hover:bg-indigo-50 text-indigo-700 rounded-xl font-bold text-sm transition-all duration-300 flex items-center gap-3 border border-gray-100 hover:border-indigo-200 hover:shadow-md hover:-translate-y-0.5">
            <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
            <div class="text-right">
                <span class="block">المعلمين</span>
                <span class="text-xs text-indigo-400 font-normal">إدارة المعلمين</span>
            </div>
        </a>
        @elseif($isTeacher)
        <a href="{{ route('attendance.index') }}" class="group px-5 py-3 bg-white hover:bg-blue-50 text-blue-700 rounded-xl font-bold text-sm transition-all duration-300 flex items-center gap-3 border border-gray-100 hover:border-blue-200 hover:shadow-md hover:-translate-y-0.5">
            <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <div class="text-right">
                <span class="block">تسجيل الحضور</span>
                <span class="text-xs text-blue-400 font-normal">حضور الطلاب</span>
            </div>
        </a>
        @endif
    </div>
</div>
{{-- CHARTS SCRIPTS                                            --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {

        // ─── Line Chart: Student Growth (جدد + متوقفين) ─────
        const growthCanvas = document.getElementById('growthChart');

        if (growthCanvas) {
            setTimeout(() => {
                const ctx = growthCanvas.getContext('2d');

                const gradientNew = ctx.createLinearGradient(0, 0, 0, 280);
                gradientNew.addColorStop(0, 'rgba(16, 185, 129, 0.3)');
                gradientNew.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

                const gradientStopped = ctx.createLinearGradient(0, 0, 0, 280);
                gradientStopped.addColorStop(0, 'rgba(239, 68, 68, 0.3)');
                gradientStopped.addColorStop(1, 'rgba(239, 68, 68, 0.0)');

                const labels = @json($chartData['labels'] ?? []);
                const newStudents = @json($chartData['new_students'] ?? []);
                const stoppedStudents = @json($chartData['stopped_students'] ?? []);

                if (labels.length === 0) {
                    growthCanvas.parentElement.innerHTML = '<div class="flex items-center justify-center h-full text-gray-400 text-sm">لا توجد بيانات</div>';
                    return;
                }

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                                label: 'طلاب جدد',
                                data: newStudents,
                                backgroundColor: gradientNew,
                                borderColor: '#10b981',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#ffffff',
                                pointBorderColor: '#10b981',
                                pointBorderWidth: 3,
                                pointRadius: 6,
                                pointHoverRadius: 9,
                                order: 1
                            },
                            {
                                label: 'طلاب متوقفين',
                                data: stoppedStudents,
                                backgroundColor: gradientStopped,
                                borderColor: '#ef4444',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#ffffff',
                                pointBorderColor: '#ef4444',
                                pointBorderWidth: 3,
                                pointRadius: 6,
                                pointHoverRadius: 9,
                                order: 2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                align: 'end',
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    padding: 20,
                                    font: {
                                        size: 12,
                                        family: 'system-ui'
                                    },
                                    rtl: true
                                }
                            },
                            tooltip: {
                                backgroundColor: '#1f2937',
                                titleColor: '#fff',
                                bodyColor: '#e5e7eb',
                                borderColor: '#374151',
                                borderWidth: 1,
                                cornerRadius: 12,
                                padding: 14,
                                rtl: true,
                                titleAlign: 'right',
                                bodyAlign: 'right',
                                callbacks: {
                                    label: function(context) {
                                        const label = context.dataset.label || '';
                                        return ' ' + label + ': ' + context.parsed.y + ' طالب';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: '#f3f4f6',
                                    drawBorder: false
                                },
                                ticks: {
                                    color: '#9ca3af',
                                    font: {
                                        size: 11,
                                        family: 'system-ui'
                                    },
                                    padding: 8
                                }
                            },
                            x: {
                                grid: {
                                    display: false,
                                    drawBorder: false
                                },
                                ticks: {
                                    color: '#9ca3af',
                                    font: {
                                        size: 11,
                                        family: 'system-ui'
                                    },
                                    padding: 8
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        animation: {
                            duration: 1500,
                            easing: 'easeOutQuart'
                        }
                    }
                });
            }, 100);
        }

        // ─── Doughnut Chart: Status Distribution ─────────────
        const statusCanvas = document.getElementById('statusChart');

        if (statusCanvas) {
            setTimeout(() => {
                const statusLabels = @json($statusChartData['labels'] ?? []);
                const statusData = @json($statusChartData['data'] ?? []);

                if (statusLabels.length === 0) {
                    statusCanvas.parentElement.innerHTML = '<div class="flex items-center justify-center h-full text-gray-400 text-sm">لا توجد بيانات</div>';
                    return;
                }

                const statusColors = {
                    'مقيد': '#10b981',
                    'منقطع': '#ef4444',
                    'مسافر': '#f59e0b',
                    'متوقف': '#6b7280',
                    'active': '#10b981',
                    'inactive': '#ef4444',
                    'traveler': '#f59e0b',
                    'other': '#6b7280'
                };

                const bgColors = statusLabels.map(label => statusColors[label] || '#9ca3af');

                new Chart(statusCanvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: statusLabels,
                        datasets: [{
                            data: statusData,
                            backgroundColor: bgColors,
                            borderColor: '#ffffff',
                            borderWidth: 4,
                            hoverOffset: 12,
                            hoverBorderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '72%',
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: '#1f2937',
                                titleColor: '#fff',
                                bodyColor: '#e5e7eb',
                                borderColor: '#374151',
                                borderWidth: 1,
                                cornerRadius: 12,
                                padding: 14,
                                rtl: true,
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const pct = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                        return ` ${context.label}: ${context.parsed} (${pct}%)`;
                                    }
                                }
                            }
                        },
                        animation: {
                            animateRotate: true,
                            animateScale: true,
                            duration: 1200,
                            easing: 'easeOutQuart'
                        }
                    }
                });

                // Custom Legend
                const legendContainer = document.getElementById('statusLegend');
                if (legendContainer) {
                    statusLabels.forEach((label, i) => {
                        const div = document.createElement('div');
                        div.className = 'flex items-center gap-1.5 bg-gray-50 px-2.5 py-1 rounded-lg border border-gray-100 hover:bg-gray-100 transition-colors cursor-pointer';
                        div.innerHTML = `
                                <span class="w-2.5 h-2.5 rounded-full" style="background-color: ${bgColors[i]}"></span>
                                <span class="text-gray-600 font-medium text-xs">${label}</span>
                                <span class="text-gray-400 text-xs">(${statusData[i]})</span>
                            `;
                        legendContainer.appendChild(div);
                    });
                }
            }, 100);
        }
    });
</script>
@endif
</x-layouts.markaz-layout>