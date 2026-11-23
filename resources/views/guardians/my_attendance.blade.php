<x-layouts.markaz-layout>
    <div class="max-w-6xl mx-auto py-8 px-4">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">سجل حضور أبنائي</h1>
                <p class="text-gray-500 mt-1">متابعة الحضور والغياب للطلاب المرتبطين بك</p>
            </div>
            <a href="{{ route('dashboard') }}" class="text-sm text-emerald-600 hover:text-emerald-800 transition">
                &larr; العودة إلى لوحة التحكم
            </a>
        </div>

        <!-- Month Filter -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
            <form action="{{ route('guardian.attendance.own') }}" method="GET"
                class="flex flex-col sm:flex-row sm:items-end gap-3">

                <div class="flex-1 max-w-xs">
                    <label for="month" class="flex items-center gap-1.5 text-sm font-medium text-gray-700 mb-1.5">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        اختر الشهر
                    </label>
                    <input type="month" id="month" name="month" value="{{ $month }}"
                        max="{{ now()->format('Y-m') }}"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 focus:ring-1 transition">
                </div>

                <button type="submit"
                    class="flex items-center justify-center gap-2 px-6 py-2.5 bg-[#0a5c36] text-white text-sm font-medium rounded-lg hover:bg-[#084b2c] active:scale-[0.98] transition-all shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    عرض السجل
                </button>

                @if(request('month') && request('month') !== now()->format('Y-m'))
                <a href="{{ route('guardian.attendance.own') }}"
                    class="text-sm text-gray-500 hover:text-gray-700 underline transition sm:mb-2.5">
                    الشهر الحالي
                </a>
                @endif
            </form>
        </div>

        @forelse($summary as $item)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6 overflow-hidden">
            <!-- Student Header -->
            <div class="p-6 border-b border-gray-100 bg-gradient-to-r from-emerald-50 to-white">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">{{ $item['student']->name }}</h2>
                            <p class="text-gray-500">{{ $item['student']->circle->name ?? '-' }}</p>
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold
                                @if($item['attendance_rate'] >= 90) text-green-600
                                @elseif($item['attendance_rate'] >= 70) text-yellow-600
                                @else text-red-600 @endif">
                            {{ $item['attendance_rate'] }}%
                        </div>
                        <div class="text-sm text-gray-500">نسبة الحضور</div>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-6">
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">{{ $item['present'] }}</div>
                    <div class="text-sm text-green-700">حاضر</div>
                </div>
                <div class="text-center p-4 bg-red-50 rounded-lg">
                    <div class="text-2xl font-bold text-red-600">{{ $item['absent'] }}</div>
                    <div class="text-sm text-red-700">غائب</div>
                </div>
                <div class="text-center p-4 bg-yellow-50 rounded-lg">
                    <div class="text-2xl font-bold text-yellow-600">{{ $item['late'] }}</div>
                    <div class="text-sm text-yellow-700">متأخر</div>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">{{ $item['excused'] }}</div>
                    <div class="text-sm text-blue-700">بعذر</div>
                </div>
            </div>

            <!-- Daily Records -->
            <div class="px-6 pb-6" x-data="{
    open: false,
    sortKey: 'date',
    sortAsc: false,
    sortBy(key) {
        if (this.sortKey === key) {
            this.sortAsc = !this.sortAsc;
        } else {
            this.sortKey = key;
            this.sortAsc = true;
        }
        const rows = Array.from($refs.tbody.querySelectorAll('tr[data-row]'));
        rows.sort((a, b) => {
            let valA = a.dataset[key];
            let valB = b.dataset[key];
            if (key === 'date') { valA = new Date(valA); valB = new Date(valB); }
            if (valA < valB) return this.sortAsc ? -1 : 1;
            if (valA > valB) return this.sortAsc ? 1 : -1;
            return 0;
        });
        rows.forEach(row => $refs.tbody.appendChild(row));
    }
}">
                <button @click="open = !open" type="button"
                    class="w-full flex items-center justify-between mb-3 group focus:outline-none">
                    <h3 class="text-sm font-semibold text-gray-700 group-hover:text-emerald-700 transition">
                        تفاصيل الأيام
                    </h3>
                    <svg class="w-4 h-4 text-gray-400 group-hover:text-emerald-600 transition-transform duration-200"
                        :class="open ? 'rotate-180' : ''"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="open" x-collapse x-cloak class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-center text-gray-600 cursor-pointer select-none hover:text-gray-900 transition"
                                    @click="sortBy('date')">
                                    <div class="flex items-center gap-1 justify-center">
                                        التاريخ
                                        <svg class="w-3 h-3" :class="sortKey === 'date' ? 'opacity-100' : 'opacity-30'"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                :d="sortKey === 'date' && !sortAsc ? 'M19 9l-7 7-7-7' : 'M5 15l7-7 7 7'" />
                                        </svg>
                                    </div>
                                </th>
                                <th class="px-4 py-2 text-center text-gray-600">اليوم</th>
                                <th class="px-4 py-2 text-center text-gray-600 cursor-pointer select-none hover:text-gray-900 transition"
                                    @click="sortBy('status')">
                                    <div class="flex items-center gap-1 justify-center">
                                        الحالة
                                        <svg class="w-3 h-3" :class="sortKey === 'status' ? 'opacity-100' : 'opacity-30'"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                :d="sortKey === 'status' && !sortAsc ? 'M19 9l-7 7-7-7' : 'M5 15l7-7 7 7'" />
                                        </svg>
                                    </div>
                                </th>
                                <th class="px-4 py-2 text-center text-gray-600">ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200" x-ref="tbody">
                            @forelse($item['student']->attendances as $record)
                            <tr class="hover:bg-gray-50" data-row
                                data-date="{{ $record->date->format('Y-m-d') }}"
                                data-status="{{ $record->status }}">
                                <td class="px-4 py-3 text-center">{{ $record->date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-center">{{ $record->date->translatedFormat('l') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($record->status === \App\Enums\AttendanceStatus::Present) bg-green-100 text-green-800
                                    @elseif($record->status === \App\Enums\AttendanceStatus::Absent) bg-red-100 text-red-800
                                    @elseif($record->status === \App\Enums\AttendanceStatus::Late) bg-yellow-100 text-yellow-800
                                    @else bg-blue-100 text-blue-800 @endif">
                                        {{ $record->status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-gray-500">{{ $record->notes ?: '-' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-center text-gray-500">
                                    لا توجد سجلات حضور في هذه الفترة
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @empty
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <p class="text-gray-500 text-lg">لا يوجد طلاب مرتبطين بك حالياً</p>
        </div>
        @endforelse
    </div>
</x-layouts.markaz-layout>