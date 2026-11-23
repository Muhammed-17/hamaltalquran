@php
$paymentRate = $totalMonths > 0 ? round(($paidCount / $totalMonths) * 100, 1) : 0;
$days = $row['raw_days'] ?? 0;
$years = floor($days / 365);
$months = floor(($days % 365) / 30);
$remainingDays = $days % 30;

$parts = [];
if ($years > 0) {
$parts[] = $years == 1 ? 'سنة' : ($years == 2 ? 'سنتان' : $years . ' سنوات');
}
if ($months > 0) {
$parts[] = $months == 1 ? 'شهر' : ($months == 2 ? 'شهران' : $months . ' أشهر');
}
if ($remainingDays > 0) {
$parts[] = $remainingDays == 1 ? 'يوم' : ($remainingDays == 2 ? 'يومان' : $remainingDays . ' أيام');
}

$overdueText = empty($parts) ? 'اليوم' : implode(' و', $parts);
@endphp

<x-layouts.markaz-layout>
    <div class="space-y-6">

        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- HEADER بطاقة الطالب                                    --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        <div class="bg-gradient-to-br from-[#0b3d2c] via-[#0d4d35] to-[#0b3d2c] rounded-3xl p-8 text-white relative overflow-hidden shadow-2xl">
            <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/4"></div>
            <div class="absolute bottom-0 left-0 w-48 h-48 bg-white/5 rounded-full blur-2xl translate-y-1/2 -translate-x-1/4"></div>

            <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="flex items-center gap-5">
                    <div class="w-16 h-16 bg-white/15 backdrop-blur-sm rounded-2xl flex items-center justify-center border border-white/20 shadow-lg">
                        <svg class="w-8 h-8 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="bg-emerald-500/20 text-emerald-100 text-xs font-bold px-2.5 py-0.5 rounded-full border border-emerald-400/30">
                                طالب مقيد
                            </span>
                        </div>
                        <h1 class="text-3xl font-black tracking-tight">{{ $student->name }}</h1>
                        <div class="flex items-center gap-3 mt-2 text-emerald-100/70 text-sm">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                {{ $student->circle?->center?->name ?? '—' }}
                            </span>
                            <span class="w-1 h-1 bg-emerald-300/50 rounded-full"></span>
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                                {{ $student->circle?->name ?? '—' }}
                            </span>
                        </div>
                    </div>
                </div>

                <a href="{{ route('subscriptions.late_and_unpaid') }}"
                    class="group flex items-center gap-2 px-5 py-2.5 bg-white/10 hover:bg-white/20 border border-white/15 rounded-xl transition-all duration-300 active:scale-95">
                    <svg class="w-4 h-4 transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span class="text-sm font-semibold">العودة للمتأخرين</span>
                </a>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- CARDS + CHART توزيع حالات السداد                        --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- بطاقات الإحصائيات --}}
            <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
                {{-- إجمالي الأشهر --}}
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300 group">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <span class="text-xs text-gray-400 font-medium">الكل</span>
                    </div>
                    <h3 class="text-2xl font-black text-gray-800">{{ $totalMonths }}</h3>
                    <p class="text-xs text-gray-400 mt-1">شهر</p>
                </div>

                {{-- مدفوع --}}
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300 group">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="text-xs text-emerald-500 font-bold bg-emerald-50 px-2 py-0.5 rounded-full">
                            {{ $paymentRate }}%
                        </span>
                    </div>
                    <h3 class="text-2xl font-black text-emerald-600">{{ $paidCount }}</h3>
                    <p class="text-xs text-gray-400 mt-1">شهر مدفوع</p>
                </div>

                {{-- متأخر --}}
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300 group">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="text-xs text-red-500 font-bold bg-red-50 px-2 py-0.5 rounded-full">
                            {{ $totalMonths - $paidCount > 0 ? round((($totalMonths - $paidCount) / $totalMonths) * 100, 1) : 0 }}%
                        </span>
                    </div>
                    <h3 class="text-2xl font-black text-red-500">{{ $unpaidCount }}</h3>
                    <p class="text-xs text-gray-400 mt-1">شهر متأخر</p>
                </div>

                {{-- Progress Bar --}}
                <div class="col-span-1 sm:col-span-3 bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-bold text-gray-700">معدل السداد</span>
                        <span class="text-sm font-black text-emerald-600">{{ $paymentRate }}%</span>
                    </div>
                    <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-emerald-400 to-emerald-600 rounded-full transition-all duration-1000 ease-out" style="width: {{ $paymentRate }}%"></div>
                    </div>
                    <div class="flex justify-between mt-2 text-xs text-gray-400">
                        <span>{{ $paidCount }} مدفوع</span>
                        <span>{{ $unpaidCount }} متأخر</span>
                    </div>
                </div>
            </div>

            {{-- Doughnut Chart --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex flex-col">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 bg-emerald-50 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-gray-700">توزيع حالات السداد</h3>
                </div>
                <div class="flex-1 relative min-h-[200px] flex items-center justify-center">
                    <div class="relative w-48 h-48">
                        <canvas id="paymentStatusChart"></canvas>
                        {{-- Center Text --}}
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <span class="text-2xl font-black text-gray-800">{{ $totalMonths }}</span>
                            <span class="text-xs text-gray-400">شهر</span>
                        </div>
                    </div>
                </div>
                {{-- Legend --}}
                <div class="flex justify-center gap-4 mt-4 text-xs">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                        <span class="text-gray-600">مدفوع ({{ $paidCount }})</span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-400"></span>
                        <span class="text-gray-600">معفي ({{ $exemptCount }})</span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-400"></span>
                        <span class="text-gray-600">متأخر ({{ $unpaidCount }})</span>
                    </span>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- جدول الأشهر المتأخرة فقط                                --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            {{-- Header --}}
            <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">الأشهر المتأخرة</h2>
                        <p class="text-xs text-gray-400">الأشهر غير المدفوعة فقط</p>
                    </div>
                </div>

                {{-- Badge --}}
                <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-sm font-bold">
                    {{ $unpaidCount }} شهر متأخر
                </span>
            </div>

            @if($unpaidCount > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-right">
                    <thead class="bg-gray-50/80">
                        <tr>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">#</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">الشهر</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">تاريخ الاستحقاق</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center">إجراء</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach(collect($timeline)->where('is_unpaid', true)->values() as $index => $row)
                        <tr class="hover:bg-red-50/30 transition-all duration-200">

                            {{-- رقم الترتيب --}}
                            <td class="px-6 py-4">
                                <span class="w-7 h-7 bg-red-100 rounded-lg flex items-center justify-center text-xs font-bold text-red-600">
                                    {{ $loop->iteration }}
                                </span>
                            </td>

                            {{-- الشهر --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center">
                                        <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-800">{{ $row['month_label'] }}</p>
                                        <p class="text-xs text-gray-400">{{ $row['month_str'] ?? '' }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- تاريخ الاستحقاق --}}
                            @php
                            $days = abs((int)($row['days_overdue'] ?? 0));
                            $years = floor($days / 365);
                            $months = floor(($days % 365) / 30);
                            $remainingDays = $days % 30;

                            $parts = [];
                            if ($years > 0) {
                            $parts[] = $years == 1 ? 'سنة' : ($years == 2 ? 'سنتان' : $years . ' سنوات');
                            }
                            if ($months > 0) {
                            $parts[] = $months == 1 ? 'شهر' : ($months == 2 ? 'شهران' : $months . ' أشهر');
                            }
                            if ($remainingDays > 0) {
                            $parts[] = $remainingDays == 1 ? 'يوم' : ($remainingDays == 2 ? 'يومان' : $remainingDays . ' أيام');
                            }

                            $overdueText = empty($parts) ? 'اليوم' : implode(' و', $parts);
                            @endphp
                            <td class="px-6 py-4">
                                <span class="text-sm text-red-600 font-medium flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    متأخر منذ {{ $overdueText }}
                                </span>
                            </td>

                            {{-- إجراء --}}
                            <td class="px-6 py-4 text-center">
                                <a href="{{ route('subscriptions.create', [
                                    'student_id' => $student->id,
                                    'circle_id'  => $student->circle_id,
                                    'month'      => $row['month_str'],
                                ]) }}"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold rounded-xl transition-all duration-200 hover:shadow-lg hover:shadow-emerald-500/25 active:scale-95">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    تسجيل سداد
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            {{-- Empty State --}}
            <div class="p-12 text-center">
                <div class="w-20 h-20 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-800 mb-2">لا توجد أشهر متأخرة!</h3>
                <p class="text-sm text-gray-400">جميع الأشهر مدفوعة بنجاح</p>
            </div>
            @endif

            {{-- Footer --}}
            <div class="px-6 py-4 bg-gray-50/50 border-t border-gray-100 flex items-center justify-between">
                <p class="text-xs text-gray-400">
                    إجمالي <span class="font-bold text-red-600">{{ $unpaidCount }}</span> شهر متأخر
                </p>
                <div class="flex items-center gap-2">
                    <span class="flex items-center gap-1 text-xs text-gray-500">
                        <span class="w-2 h-2 bg-emerald-400 rounded-full"></span>
                        {{ $paidCount }} مدفوع
                    </span>
                    <span class="flex items-center gap-1 text-xs text-gray-500">
                        <span class="w-2 h-2 bg-red-400 rounded-full"></span>
                        {{ $unpaidCount }} متأخر
                    </span>
                </div>
            </div>
        </div>

    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- SCRIPTS                                                  --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('paymentStatusChart');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($chartData['labels']) !!},
                datasets: [{
                    data: {!! json_encode($chartData['data']) !!},
                    backgroundColor: {!! json_encode($chartData['colors']) !!},
                    borderColor: ['#10b981', '#3b82f6', '#ef4444'],
                    borderWidth: 2,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return ` ${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                animation: {
                    animateScale: true,
                    animateRotate: true
                }
            }
        });
    });
</script>
</x-layouts.markaz-layout>