<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6 mb-8">
        <div class="text-right w-full md:w-auto z-10">
            <h1 class="text-3xl font-black mb-2">جولات تحصيل الأوسمة</h1>
            <p class="text-emerald-100/80 text-sm font-medium">{{ $rounds->total() }} جولة</p>
        </div>
        @can('badges.collections.create')
            <a href="{{ route('badges.collections.create') }}" class="px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-2xl shadow-lg active:scale-95">+ جولة جديدة</a>
        @endcan
        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6" dir="rtl">
        <form method="GET" class="flex flex-col lg:flex-row gap-4 items-end">
            <div class="w-full lg:w-56">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">الحلقة</label>
                <select name="circle_id" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    <option value="">كل الحلقات</option>
                    @foreach ($circles as $c)<option value="{{ $c->id }}" @selected(request('circle_id') == $c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="w-full lg:w-44">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">الحالة</label>
                <select name="status" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    <option value="">الكل</option>
                    @foreach (\App\Enums\Badges\CollectionRoundStatus::cases() as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>@endforeach
                </select>
            </div>
            <button class="px-6 py-2.5 bg-[#0a5c36] text-white font-bold rounded-xl text-sm">تصفية</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto" dir="rtl">
        <table class="w-full text-right">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">الحلقة</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">المعلم</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">تاريخ التحصيل</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">البنود</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">الحالة</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rounds as $r)
                    <tr>
                        <td class="px-6 py-4 text-sm font-bold">{{ $r->circle->name }}</td>
                        <td class="px-6 py-4 text-sm">{{ $r->teacher->name }}</td>
                        <td class="px-6 py-4 text-sm">{{ $r->collection_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-4 text-sm">{{ $r->items_count }}</td>
                        <td class="px-6 py-4 text-sm">
                            <span class="px-2 py-1 rounded-lg text-xs font-bold {{ match($r->status->value) { 'completed' => 'bg-emerald-50 text-emerald-700', 'cancelled' => 'bg-red-50 text-red-600', default => 'bg-amber-50 text-amber-700' } }}">{{ $r->status->label() }}</span>
                        </td>
                        <td class="px-6 py-4 text-sm"><a href="{{ route('badges.collections.show', $r) }}" class="text-[#0a5c36] font-bold hover:underline">فتح</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400 text-sm">لا جولات مطابقة</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6"><x-pagination :paginator="$rounds" /></div>
</x-layouts.markaz-layout>
