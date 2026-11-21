<x-layouts.markaz-layout>
    <x-slot name="title">جلسات المجموعة</x-slot>

    <div class="bg-white rounded-xl shadow-sm p-5 mb-6">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">

            <form action="{{ route('group-session-plans.index') }}" method="GET" class="flex flex-wrap gap-3 flex-1">

                <select
                    name="circle_id"
                    class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#0a5c36] outline-none">

                    <option value="">جميع الحلقات</option>

                    @foreach($circles ?? [] as $circle)
                    <option value="{{ $circle->id }}"
                        {{ request('circle_id') == $circle->id ? 'selected' : '' }}>
                        {{ $circle->name }}
                    </option>
                    @endforeach

                </select>

                <button
                    type="submit"
                    class="bg-[#0a5c36] hover:bg-[#0d7a48] text-white px-5 py-2.5 rounded-lg text-sm transition-colors">
                    <i class="fas fa-filter ml-2"></i>
                    تصفية
                </button>

            </form>

            @can('create', App\Models\GroupSessionPlan::class)
            <a href="{{ route('group-session-plans.create') }}"
                class="bg-[#0a5c36] hover:bg-[#0d7a48] text-white px-5 py-2.5 rounded-lg text-sm transition-colors whitespace-nowrap">
                <i class="fas fa-plus ml-2"></i>
                جلسة جديدة
            </a>
            @endcan

        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-bold text-lg text-gray-800"><i class="fas fa-users ml-2 text-blue-500"></i>جلسات المجموعة</h3>
        </div>
        @if($groupSessionPlans->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">#</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الجلسة</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الوقت</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">المحتوى المخطط</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">المحتوى المنفذ</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($groupSessionPlans as $session)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-3 text-gray-500">{{ $session->id }}</td>
                        <td class="px-4 py-3"><span class="font-medium text-gray-800">{{ $session->session_name }}</span><br><span class="text-gray-400 text-xs">{{ $session->circle?->name ?? '-' }}</span></td>
                        <td class="px-4 py-3"><span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">{{ $session->start_time?->format('H:i') }} - {{ $session->end_time?->format('H:i') }}</span></td>
                        <td class="px-4 py-3 text-gray-600">{{ Str::limit($session->planned_content, 40) }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $session->completed_content ? Str::limit($session->completed_content, 40) : '-' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                @can('view', $session)
                                <a href="{{ route('group-session-plans.show', $session) }}" class="w-7 h-7 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center"><i class="fas fa-eye text-xs"></i></a>
                                @endcan
                                @can('update', $session)
                                <a href="{{ route('group-session-plans.edit', $session) }}" class="w-7 h-7 bg-amber-50 hover:bg-amber-100 text-amber-600 rounded-lg flex items-center justify-center"><i class="fas fa-edit text-xs"></i></a>
                                @endcan
                                @can('delete', $session)
                                <form action="{{ route('group-session-plans.destroy', $session) }}" method="POST" class="inline" id="delete-session-{{ $session->id }}">
                                    @csrf @method('DELETE')
                                    <button type="button" onclick="confirmDelete('delete-session-{{ $session->id }}')" class="w-7 h-7 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg flex items-center justify-center"><i class="fas fa-trash text-xs"></i></button>
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-gray-100">{{ $groupSessionPlans->links() }}</div>
        @else
        <div class="text-center py-16">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3"><i class="fas fa-users text-2xl text-gray-300"></i></div>
            <h4 class="text-gray-500 font-medium">لا توجد جلسات</h4>
            <p class="text-gray-400 text-sm">أضف جلسات للمجموعات</p>
        </div>
        @endif
    </div>
</x-layouts.markaz-layout>