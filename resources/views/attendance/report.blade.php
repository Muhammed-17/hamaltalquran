<x-layouts.markaz-layout>
    <x-slot name="title">تقرير الحضور والغياب</x-slot>

    <div class="max-w-7xl mx-auto py-8 px-4">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">تقرير الحضور والغياب</h1>

        <!-- Export Buttons -->
        <div class="flex flex-wrap gap-3 mb-6">
            <a href="{{ route('attendance.export.excel', request()->all()) }}"
               class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                تصدير Excel
            </a>
            <a href="{{ route('attendance.export.monthly', request()->all()) }}"
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                تقرير شهري
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            @php
                $statLabels = ['present' => ['حاضر', 'green'], 'absent' => ['غائب', 'red'], 'late' => ['متأخر', 'yellow'], 'excused' => ['بعذر', 'blue']];
            @endphp
            @foreach($statLabels as $key => [$label, $color])
                @php $stat = $stats->firstWhere('status', $key); @endphp
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">{{ $label }}</p>
                            <p class="text-3xl font-bold text-{{ $color }}-600">{{ $stat?->count ?? 0 }}</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-{{ $color }}-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-{{ $color }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                @if($key === 'present')
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                @elseif($key === 'absent')
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                @elseif($key === 'late')
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                @else
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                @endif
                            </svg>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Monthly Chart -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">إحصائيات الأشهر</h2>
            <div class="h-64"><canvas id="monthlyChart"></canvas></div>
        </div>

        <!-- Daily Stats -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">الحضور اليومي (آخر 7 أيام)</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-gray-600">التاريخ</th>
                            <th class="px-4 py-3 text-right text-gray-600">عدد الحضور</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($dailyStats as $stat)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">{{ $stat->date }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        {{ $stat->count }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-8 text-center text-gray-400">لا توجد بيانات</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Circle Comparison -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">مقارنة الحلقات</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-gray-600">الحلقة</th>
                            <th class="px-4 py-3 text-right text-gray-600">إجمالي</th>
                            <th class="px-4 py-3 text-right text-gray-600">حاضر</th>
                            <th class="px-4 py-3 text-right text-gray-600">غائب</th>
                            <th class="px-4 py-3 text-right text-gray-600">متأخر</th>
                            <th class="px-4 py-3 text-right text-gray-600">بعذر</th>
                            <th class="px-4 py-3 text-right text-gray-600">نسبة الحضور</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($circleStats as $circleName => $data)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium">{{ $circleName }}</td>
                                <td class="px-4 py-3">{{ $data['total'] }}</td>
                                <td class="px-4 py-3 text-green-600">{{ $data['present'] }}</td>
                                <td class="px-4 py-3 text-red-600">{{ $data['absent'] }}</td>
                                <td class="px-4 py-3 text-yellow-600">{{ $data['late'] }}</td>
                                <td class="px-4 py-3 text-blue-600">{{ $data['excused'] }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-20 h-2 bg-gray-200 rounded-full overflow-hidden">
                                            <div class="h-full bg-green-500 rounded-full" style="width: {{ $data['rate'] }}%"></div>
                                        </div>
                                        <span class="text-sm">{{ $data['rate'] }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400">لا توجد بيانات</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
    <script>
        const ctx = document.getElementById('monthlyChart');
        const monthlyData = @json($monthlyStats);
        const labels = Object.keys(monthlyData);
        const presentData = labels.map(m => monthlyData[m].find(s => s.status === 'present')?.count ?? 0);
        const absentData = labels.map(m => monthlyData[m].find(s => s.status === 'absent')?.count ?? 0);

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'حاضر', data: presentData, backgroundColor: '#10B981' },
                    { label: 'غائب', data: absentData, backgroundColor: '#EF4444' },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>
</x-layouts.markaz-layout>