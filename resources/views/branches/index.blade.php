@php
$sortLink = fn($field) => request()->fullUrlWithQuery([
'sort' => $field,
'dir' => request('sort') === $field && request('dir', 'asc') === 'asc' ? 'desc' : 'asc',
]);
$sortIcon = fn($field) => request('sort') === $field
? (request('dir', 'asc') === 'asc' ? '↑' : '↓')
: '';
@endphp

<x-layouts.markaz-layout>
    <div dir="rtl"
        class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6 mb-8">
        <div class="text-right w-full md:w-auto z-10">
            <h1 class="text-3xl font-black mb-2">إدارة المقرات</h1>
            <p class="text-emerald-100/80 text-sm font-medium">
                @if(request()->anyFilled(['q', 'center_id']))
                {{ $branches->total() }} نتيجة
                @else
                {{ $branches->total() }} فرع مسجل في النظام
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-4 w-full md:w-auto">
            <a href="{{ route('branches.create') }}"
                class="w-full md:w-auto px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-2xl flex items-center justify-center gap-2 transition-all shadow-lg hover:shadow-emerald-500/20 active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                جديد
            </a>
        </div>

        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
    </div>

    {{-- ─── فلاتر التصفية ─── --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6">
        <form method="GET" action="{{ route('branches.index') }}" class="flex flex-col lg:flex-row gap-4 items-end" dir="rtl">
            {{-- البحث --}}
            <div class="w-full lg:flex-1">
                <label for="filter_q" class="block text-xs font-bold text-gray-400 mb-1.5">البحث بالاسم</label>
                <input id="filter_q" type="search" name="q" value="{{ request('q') }}"
                    placeholder="ابحث باسم المقر..."
                    class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-1 focus:ring-[#0a5c36] focus:border-[#0a5c36] transition-all bg-white text-right">
            </div>

            {{-- فلتر المركز --}}
            <div class="w-full lg:w-48">
                <label for="filter_center_id" class="block text-xs font-bold text-gray-400 mb-1.5">المركز</label>
                <x-searchable-select
                    name="center_id"
                    placeholder="كل المراكز"
                    search-placeholder="بحث باسم المركز..."
                    :default-value="request('center_id', '')"
                    :options="json_encode($centers->map(fn($c) => ['value' => (string)$c->id, 'label' => $c->name])->toArray())" />
            </div>

            {{-- زر البحث --}}
            <button type="submit"
                class="w-full lg:w-auto px-5 py-2.5 bg-[#0a5c36] hover:bg-[#08492a] text-white font-bold rounded-xl text-sm transition-all text-center">
                بحث
            </button>

            {{-- زر مسح الفلاتر --}}
            @if(request()->anyFilled(['q', 'center_id']))
            <a href="{{ route('branches.index') }}"
                class="w-full lg:w-auto px-5 py-2.5 bg-gray-50 hover:bg-gray-100 text-gray-500 hover:text-gray-700 font-bold border border-gray-200 rounded-xl text-sm transition-all text-center">
                مسح الفلاتر
            </a>
            @endif
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600 select-none">
                            <a href="{{ $sortLink('name') }}" class="flex items-center gap-1 hover:text-gray-800">
                                <span>اسم المقر</span>
                                <span class="text-xs text-gray-400">{{ $sortIcon('name') }}</span>
                            </a>
                        </th>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600">المركز</th>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600">المشرفون</th>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600">العنوان</th>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600 select-none">
                            <a href="{{ $sortLink('established_at') }}" class="flex items-center gap-1 hover:text-gray-800">
                                <span>تاريخ الإنشاء</span>
                                <span class="text-xs text-gray-400">{{ $sortIcon('established_at') }}</span>
                            </a>
                        </th>
                        <th class="px-6 py-4 text-sm font-bold text-gray-600 text-left">الإجراءات</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @forelse($branches as $branch)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 font-bold text-gray-800">
                            {{ $branch->name }}
                        </td>

                        <td class="px-6 py-4 text-gray-600">
                            {{ $branch->center?->name ?? '—' }}
                        </td>

                        <td class="px-6 py-4 text-gray-600">
                            @if($branch->supervisors->isNotEmpty())
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($branch->supervisors as $supervisor)
                                <span class="inline-flex items-center px-2.5 py-1 bg-emerald-50 text-emerald-700 text-xs font-semibold rounded-lg">
                                    {{ $supervisor->user?->name ?? $supervisor->name }}
                                </span>
                                @endforeach
                            </div>
                            @else
                            <span class="text-gray-400 text-xs">لا يوجد مشرف</span>
                            @endif
                        </td>

                        <td class="px-6 py-4 text-gray-600">
                            {{ $branch->address ?? '—' }}
                        </td>

                        <td class="px-6 py-4 text-gray-600">
                            {{ $branch->established_at?->format('Y-m-d') ?? '—' }}
                        </td>

                        <td class="px-6 py-4 text-left">
                            <div class="flex items-center justify-end gap-3">

                                <a href="{{ route('branches.edit', $branch) }}"
                                    class="text-blue-500 hover:text-blue-700 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>

                                <form method="POST" action="{{ route('branches.destroy', $branch->id) }}"
                                    onsubmit="confirmDelete(event, { name: '{{ e($branch->name) }}', type: 'المقر' })"
                                    class="text-red-400 hover:text-red-600 transition">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            @if(request()->anyFilled(['q', 'center_id']))
                            لا توجد فروع تطابق الفلاتر المحددة.
                            @else
                            لا توجد فروع مسجلة حالياً.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ─── الترقيم الموحّد ─── --}}
        <x-pagination :paginator="$branches" />
    </div>
</x-layouts.markaz-layout>