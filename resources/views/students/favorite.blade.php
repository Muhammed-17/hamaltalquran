@php
$isAdmin = auth()->user()->hasRole('admin');
$hasFilters = request()->anyFilled(['search', 'teacher_id', 'circle_id', 'date_from', 'date_to']);
@endphp

<x-layouts.markaz-layout>

    <div class="space-y-6">

        {{-- Header --}}
        <div class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6">
            <div class="order-2 md:order-2 flex flex-wrap items-center gap-4 w-full md:w-auto">
                <a href="{{ route('students.index') }}"
                    class="w-full md:w-auto px-6 py-3 bg-white/10 hover:bg-white/20 text-white font-bold rounded-2xl flex items-center justify-center gap-2 transition-all border border-white/20 active:scale-95">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    الرجوع لقائمة الطلاب
                </a>
            </div>

            <div class="order-1 md:order-1 text-right w-full md:w-auto z-10">
                <h1 class="text-3xl font-black mb-2 flex items-center gap-2 justify-end">
                    <svg class="w-7 h-7 text-yellow-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                    </svg>
                    الطلاب المفضلون
                </h1>
                <p class="text-emerald-100/80 text-sm font-medium">
                    @if($hasFilters)
                    {{ $favorites->total() }} نتيجة
                    @else
                    {{ $favorites->total() }} طالب في قائمة المفضلة
                    @endif
                </p>
            </div>

            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
        </div>

        {{-- فلاتر --}}
        <form id="favorites-filter" method="GET" action="{{ route('favorites.index') }}" class="bg-white p-4 rounded-xl border border-gray-100 space-y-4">

            <div class="flex flex-col lg:flex-row gap-4">
                <div class="flex-1 relative">
                    <input type="search" name="search" value="{{ request('search') }}"
                        placeholder="بحث بالاسم..."
                        class="w-full px-4 py-2.5 pr-10 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#10b981]/50 focus:border-emerald-500">
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

                @if($hasFilters)
                <a href="{{ route('favorites.index') }}"
                    class="px-5 py-2.5 border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium rounded-lg transition-colors shrink-0 text-center">
                    إعادة تعيين
                </a>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                @if($isAdmin && isset($teachers) && $teachers->count() > 1)
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">المعلم</label>
                    <x-searchable-select
                        name="teacher_id"
                        :options="$teachers->map(fn($teacher) => ['value' => (string) $teacher->id, 'label' => $teacher->user->name ?? ('معلم #' . $teacher->id)])->toArray()"
                        placeholder="كل المعلمين"
                        searchPlaceholder="ابحث عن معلم..."
                        :defaultValue="request('teacher_id', '')" />
                </div>
                @endif

                @if(isset($circles) && $circles->count() > 1)
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">الحلقة</label>
                    <x-searchable-select
                        name="circle_id"
                        :options="$circles->map(fn($circle) => ['value' => (string) $circle->id, 'label' => $circle->name])->values()->toArray()"
                        placeholder="كل الحلقات"
                        searchPlaceholder="ابحث عن حلقة..."
                        :defaultValue="request('circle_id', '')" />
                </div>
                @endif

                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">من تاريخ</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                        max="{{ request('date_to') }}"
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#10b981]/50 focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">إلى تاريخ</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                        min="{{ request('date_from') }}"
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#10b981]/50 focus:border-emerald-500">
                </div>
            </div>
        </form>

        {{-- Table --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-x-auto">
            <table class="w-full text-right min-w-[900px]">
                <thead class="bg-gray-50 text-gray-500 text-sm">
                    <tr>
                        <th class="py-4 px-6 font-medium rounded-tr-xl">اسم الطالب</th>
                        <th class="py-4 px-6 font-medium">الحلقة</th>
                        <th class="py-4 px-6 font-medium">أضافه</th>
                        <th class="py-4 px-6 font-medium">تاريخ الإضافة</th>
                        <th class="py-4 px-6 font-medium">سبب الإضافة</th>
                        <th class="py-4 px-6 font-medium rounded-tl-xl">إجراء</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @forelse($favorites as $favorite)
                    <tr class="hover:bg-gray-50/50">
                        <td class="py-4 px-6 font-medium text-gray-800">
                            {{ $favorite->student->name ?? '—' }}
                        </td>
                        <td class="py-4 px-6 text-gray-600">
                            {{ $favorite->circle?->name ?? '—' }}
                        </td>
                        <td class="py-4 px-6 text-gray-600">
                            {{ $favorite->teacher?->user?->name ?? 'الإدارة' }}
                        </td>
                        <td class="py-4 px-6 text-gray-500 text-sm">
                            {{ $favorite->created_at->format('Y-m-d') }}
                        </td>
                        <td class="py-4 px-6 text-gray-600 max-w-xs truncate" title="{{ $favorite->reason }}">
                            {{ $favorite->reason }}
                        </td>
                        <td class="py-4 px-6">
                            <div class="flex items-center justify-end gap-3">
                                @if($favorite->student)
                                <a href="{{ route('students.show', $favorite->student) }}"
                                    class="text-green-400 hover:text-green-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a>

                                <button type="button"
                                    onclick="openFavoriteModal({{ $favorite->student->id }}, true)"
                                    class="text-yellow-500 hover:text-red-500 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                        fill="currentColor" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                    </svg>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-12 px-6 text-center text-gray-500">
                            @if($hasFilters)
                            <span>لا توجد نتائج مطابقة لبحثك أو الفلاتر.</span>
                            @else
                            <span>لا يوجد طلاب في قائمة المفضلة حالياً.</span>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            <x-pagination :paginator="$favorites" />
        </div>

    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initFavorites({
                admin: @json($isAdmin),
                teachers: @json(($teachers ?? collect())->map(fn($t) => ['id' => $t->id, 'name' => $t->user->name ?? ('معلم #'.$t->id)])),
                token: @json(csrf_token()),
                baseUrl: @json(url('/students')),
            });

            const filterForm = document.getElementById('favorites-filter');

            window.addEventListener('searchable-change', function(e) {
                if (['teacher_id', 'circle_id'].includes(e.detail.name)) {
                    filterForm?.submit();
                }
            });

            filterForm?.querySelectorAll('input[type="date"]').forEach(function(input) {
                input.addEventListener('change', function() {
                    filterForm.submit();
                });
            });
        });
    </script>
    @endpush

</x-layouts.markaz-layout>