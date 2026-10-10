<x-layouts.markaz-layout>
    <div class="space-y-6">

        {{-- ─── Header ─── --}}
        <div class="bg-[#0a4d31] rounded-3xl p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6">
            <div class="text-right w-full md:w-auto z-10">
                <h1 class="text-3xl font-black mb-2">سجل المتابعة التاريخي</h1>
                <p class="text-emerald-100/80 text-sm font-medium">عرض ومراجعة كافة سجلات الحضور والغياب</p>
            </div>

            <div class="flex gap-4 z-10">
                @can('create', App\Models\Attendance::class)
                <a href="{{ route('attendance.create') }}"
                    class="px-6 py-3 bg-white/10 hover:bg-white/20 border border-white/20 rounded-2xl text-white font-bold transition-all">
                    تسجيل الحضور
                </a>
                @endcan

                @can('view reports')
                <a href="{{ route('attendance.report') }}"
                    class="px-6 py-3 bg-white/10 hover:bg-white/20 border border-white/20 rounded-2xl text-white font-bold transition-all">
                    عرض الإحصائيات
                </a>
                @endcan

                @can('view attendance')
                <a href="{{ route('attendance.sequential-absences') }}"
                    class="px-6 py-3 bg-white/10 hover:bg-white/20 border border-white/20 rounded-2xl text-white font-bold transition-all">
                    الغيابات المتكررة
                </a>
                @endcan
            </div>

            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
        </div>

        {{-- ─── Filters ─── --}}
        <div class="bg-white p-6 rounded-[30px] shadow-sm border border-gray-100">
            <form action="{{ route('attendance.index') }}" method="GET" class="bg-white p-4 rounded-xl border border-gray-100 space-y-4">

                {{-- صف البحث والأزرار --}}
                <div class="flex flex-col lg:flex-row gap-4">
                    <div class="flex-1 relative">
                        <input type="search" name="search" value="{{ $search }}"
                            placeholder="بحث باسم الطالب..."
                            class="w-full px-4 py-2.5 pr-10 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500">
                        <svg class="w-5 h-5 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>

                    <button type="submit"
                        class="px-6 py-2.5 bg-[#0a5c36] hover:bg-[#08492a] text-white font-bold rounded-lg transition-colors shrink-0">
                        بحث
                    </button>

                    @if(request()->hasAny(['circle_id', 'center_id', 'status', 'user_id', 'date', 'search']))
                    <a href="{{ route('attendance.index') }}"
                        class="px-5 py-2.5 border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium rounded-lg transition-colors shrink-0 text-center">
                        إعادة تعيين
                    </a>
                    @endif
                </div>

                {{-- صف الفلاتر --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                    {{-- فلتر الحلقة --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">الحلقة</label>
                        <x-searchable-select
                            name="circle_id"
                            placeholder="كل الحلقات"
                            search-placeholder="ابحث عن حلقة..."
                            :default-value="$selectedCircleId"
                            :options="$circles->map(fn($c) => ['value' => $c->id, 'label' => $c->name])" />
                    </div>

                    {{-- فلتر الفرع --}}
                    @if(auth()->user()->hasRole(['admin', 'general_manager']) && $centers->isNotEmpty())
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">الفرع</label>
                        <x-searchable-select
                            name="center_id"
                            placeholder="كل الفروع"
                            search-placeholder="ابحث عن فرع..."
                            :default-value="$selectedCenterId"
                            :options="$centers->map(fn($c) => ['value' => $c->id, 'label' => $c->name])" />
                    </div>
                    @endif

                    {{-- فلتر المسجل --}}
                    @if(auth()->user()->hasRole(['admin', 'general_manager', 'manager']))
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">المسجل</label>
                        <x-searchable-select
                            name="user_id"
                            placeholder="كل المسجلين"
                            search-placeholder="ابحث عن مسجل..."
                            :default-value="$selectedRegistrarId"
                            :options="$registrars->map(fn($r) => ['value' => $r->id, 'label' => $r->name])" />
                    </div>
                    @endif

                    {{-- فلتر الحالة --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">الحالة</label>
                        <select name="status"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500/50">
                            <option value="">كل الحالات</option>
                            <option value="present" {{ $selectedStatus === 'present' ? 'selected' : '' }}>حاضر</option>
                            <option value="absent" {{ $selectedStatus === 'absent'  ? 'selected' : '' }}>غائب</option>
                            <option value="late" {{ $selectedStatus === 'late'    ? 'selected' : '' }}>متأخر</option>
                            <option value="excused" {{ $selectedStatus === 'excused' ? 'selected' : '' }}>بعذر</option>
                        </select>
                    </div>

                    {{-- فلتر التاريخ --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">التاريخ</label>
                        <input type="date" name="date" value="{{ $selectedDate }}"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500/50">
                    </div>

                </div>

            </form>
        </div>

        {{-- ─── Export ─── --}}
        @can('export data')
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('attendance.export.excel', request()->except('page')) }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-100 rounded-2xl text-emerald-700 font-bold text-sm shadow-sm hover:bg-emerald-50 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                تصدير Excel

                {{-- ✅ الزرار الجديد --}}
                <a href="{{ route('attendance.export.monthly', request()->except('page')) }}"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-100 rounded-2xl text-blue-600 font-bold text-sm shadow-sm hover:bg-blue-50 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    تقرير شهري
                </a>
        </div>
    </div>
    @endcan

    {{-- ─── Table ─── --}}
    <div class="bg-white rounded-[40px] shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            @php
            $toggleSort = request()->fullUrlWithQuery([
            'sort_order' => $sortOrder === 'desc' ? 'asc' : 'desc',
            'page' => 1
            ]);
            @endphp
            <table class="w-full text-right">
                <thead class="bg-gray-50 border-b border-gray-100 font-black text-gray-400 text-sm uppercase">
                    <tr>
                        <th class="px-8 py-5 text-center">#</th>
                        <th class="px-8 py-5 cursor-pointer select-none"
                            onclick="window.location='{{ $toggleSort }}'">
                            <div class="flex items-center gap-1">
                                تاريخ
                                @if($sortOrder === 'desc')
                                <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                                @else
                                <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                </svg>
                                @endif
                            </div>
                        </th>
                        <th class="px-8 py-5">اسم الطالب</th>
                        <th class="px-8 py-5 min-w-40">الحلقة</th>
                        <th class="px-8 py-5">الحالة</th>
                        <th class="px-8 py-5 text-center">كروت</th>
                        <th class="px-8 py-5">ملاحظات</th>
                        <th class="px-8 py-5">المسجل</th>
                        @canany(['update', 'delete'], App\Models\Attendance::class)
                        <th class="px-8 py-5 text-center">الإجراءات</th>
                        @endcanany
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($records as $record)
                    <tr class="hover:bg-emerald-50/30 transition-all group">
                        {{-- رقم السجل الكلي المعتمد على الباجينيشن --}}
                        <td class="px-8 py-6 font-bold text-gray-500 text-center">
                            {{ ($records->currentPage() - 1) * $records->perPage() + $loop->iteration }}
                        </td>
                        {{-- التاريخ مع فورمات آمن في حال كان كائن كربون --}}
                        <td class="px-8 py-6 font-bold text-gray-700">
                            {{ is_string($record->date) ? $record->date : $record->date->format('Y-m-d') }}
                        </td>
                        <td class="px-8 py-6">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center text-gray-400 group-hover:bg-white group-hover:text-emerald-500 shadow-sm transition-all">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <span class="font-bold text-gray-800">{{ $record->student->name ?? '-' }}</span>
                            </div>
                        </td>
                        <td class="px-8 py-6 min-w-40 text-center">
                            <span class="px-4 py-2 rounded-xl text-gray-600 font-bold text-xs">
                                {{ $record->student->circle->name ?? '-' }}
                            </span>
                        </td>
                        {{-- عرض الحالات بالتصميم الأنيق المطور --}}
                        <td class="px-8 py-6 font-black">
                            @if ($record->status === 'present')
                            <span class="text-emerald-500 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>حاضر
                            </span>
                            @elseif($record->status === 'absent')
                            <span class="text-red-500 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-red-500"></span>غائب
                            </span>
                            @elseif($record->status === 'late')
                            <span class="text-amber-500 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>متأخر
                            </span>
                            @else
                            <span class="text-blue-500 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>بعذر
                            </span>
                            @endif
                        </td>
                        <td class="px-8 py-6 text-center font-bold text-gray-700">
                            {{ $record->card ?? 0 }}
                        </td>
                        {{-- عمود الملاحظات --}}
                        <td class="px-8 py-6 text-gray-500 font-medium max-w-xs truncate">
                            {{ $record->notes ?: '-' }}
                        </td>
                        <td class="px-8 py-6 text-gray-500 font-medium">
                            {{ $record->user->name ?? 'بواسطة المعلم' }}
                        </td>
                        {{-- الإجراءات المعتمدة على الـ Policy والموديل الحالي --}}
                        @if(auth()->user()->can('update', $record) || auth()->user()->hasRole('admin'))
                        <td class="px-8 py-6">
                            <div class="flex items-center justify-center gap-3">
                                <a href="{{ route('attendance.show', $record) }}"
                                    class="text-green-400 hover:text-green-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a>
                                @can('update', $record)
                                <a href="{{ route('attendance.edit', $record) }}"
                                    class="w-9 h-9 flex items-center justify-center bg-amber-50 hover:bg-amber-100 text-amber-600 rounded-xl transition-colors" title="تعديل">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                                @endcan

                                @can('delete', $record)
                                <form action="{{ route('attendance.destroy', $record) }}" method="POST"
                                    onsubmit="return confirm('هل أنت متأكد من حذف هذا السجل؟')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                        class="w-9 h-9 flex items-center justify-center bg-red-50 hover:bg-red-100 text-red-600 rounded-xl transition-colors" title="حذف">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-8 py-20 text-center text-gray-400 font-medium">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            <p>لا توجد سجلات مطابقة لهذه الفلاتر.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$records" />
    </div>

    </div>
</x-layouts.markaz-layout>