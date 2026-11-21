<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white shadow-xl mb-8">
        <h1 class="text-3xl font-black mb-2">سجل عمليات الأوسمة</h1>
        <p class="text-emerald-100/80 text-sm font-medium">{{ $logs->total() }} عملية مسجلة</p>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6" dir="rtl">
        <form method="GET" class="flex flex-col lg:flex-row gap-4 items-end">
            <div class="w-full lg:w-52">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">نوع العملية (بحث جزئي)</label>
                <input type="text" name="action" value="{{ request('action') }}" placeholder="distribution / stock ..." class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
            </div>
            <div class="w-full lg:w-52">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">الجدول المتأثر</label>
                <input type="text" name="entity_type" value="{{ request('entity_type') }}" placeholder="badge_entitlements ..." class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
            </div>
            <div class="w-full lg:w-44">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">من تاريخ</label>
                <input type="date" name="from" value="{{ request('from') }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
            </div>
            <div class="w-full lg:w-44">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">إلى تاريخ</label>
                <input type="date" name="to" value="{{ request('to') }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
            </div>
            <button class="px-6 py-2.5 bg-[#0a5c36] text-white font-bold rounded-xl text-sm">تصفية</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto" dir="rtl">
        <table class="w-full text-right">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">المستخدم</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">العملية</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">السجل</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">القيم الجديدة</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">السبب</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">التاريخ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-6 py-4 text-sm font-bold">{{ $log->user->name ?? 'النظام' }}</td>
                        <td class="px-6 py-4 text-sm">{{ $log->action }}</td>
                        <td class="px-6 py-4 text-sm">{{ $log->entity_type }} #{{ $log->entity_id }}</td>
                        <td class="px-6 py-4 text-xs text-gray-500 max-w-xs truncate">{{ $log->new_values ? json_encode($log->new_values, JSON_UNESCAPED_UNICODE) : '—' }}</td>
                        <td class="px-6 py-4 text-sm">{{ $log->reason ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm">{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400 text-sm">لا عمليات مطابقة</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6"><x-pagination :paginator="$logs" /></div>
</x-layouts.markaz-layout>
