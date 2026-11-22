<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6 mb-8">
        <div class="text-right w-full md:w-auto z-10">
            <h1 class="text-3xl font-black mb-2">أنواع الأوسمة</h1>
            <p class="text-emerald-100/80 text-sm font-medium">{{ $types->total() }} نوع مسجل</p>
        </div>
        @can('badges.types.create')
            <a href="{{ route('badges.types.create') }}" class="px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-2xl shadow-lg active:scale-95">+ نوع جديد</a>
        @endcan
        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto" dir="rtl">
        <table class="w-full text-right">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">الوسام</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">رصيد الإدارة</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">تسليمات</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">استحقاقات</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">توزيعات</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">الحالة</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($types as $type)
                    <tr>
                        <td class="px-6 py-4 text-sm font-bold">
                            {{ $type->name }}
                        </td>
                        <td class="px-6 py-4 text-sm font-black">{{ $adminBalances[$type->id] ?? 0 }}</td>
                        <td class="px-6 py-4 text-sm">{{ $type->handovers_count }}</td>
                        <td class="px-6 py-4 text-sm">{{ $type->entitlements_count }}</td>
                        <td class="px-6 py-4 text-sm">{{ $type->distributions_count }}</td>
                        <td class="px-6 py-4 text-sm">
                            @if ($type->is_active)<span class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-bold">مفعّل</span>
                            @else<span class="px-2 py-1 rounded-lg bg-red-50 text-red-600 text-xs font-bold">معطّل</span>@endif
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <span class="flex gap-2">
                                <a href="{{ route('badges.types.show', $type) }}" class="text-[#0a5c36] font-bold hover:underline">عرض</a>
                                @can('badges.types.update')
                                    <a href="{{ route('badges.types.edit', $type) }}" class="text-blue-600 font-bold hover:underline">تعديل</a>
                                @endcan
                                @can('delete', $type)
                                    <form method="POST" action="{{ route('badges.types.destroy', $type) }}" onsubmit="return confirm('حذف نوع الوسام نهائيًا؟')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 font-bold hover:underline">حذف</button>
                                    </form>
                                @endcan
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-10 text-center text-gray-400 text-sm">لا توجد أنواع أوسمة بعد</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6"><x-pagination :paginator="$types" /></div>
</x-layouts.markaz-layout>
