<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 text-white shadow-xl mb-8 flex flex-col md:flex-row justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black">جولة تحصيل — {{ $round->circle->name }}</h1>
            <p class="text-emerald-100/80 text-sm">المعلم {{ $round->teacher->name }} — {{ $round->collection_date->format('Y-m-d') }} — الحالة: {{ $round->status->label() }}</p>
        </div>
        <div class="flex gap-3 items-start">
            @if ($round->isDraft())
                @can('complete', $round)
                    <form method="POST" action="{{ route('badges.collections.complete', $round) }}" onsubmit="return confirm('اعتماد الجولة؟ بعد الاعتماد تدخل الكميات المحصلة في الأرصدة ولا يمكن التعديل.')">
                        @csrf
                        <button class="px-5 py-2.5 bg-emerald-500 text-white rounded-xl font-bold text-sm">اعتماد الجولة</button>
                    </form>
                @endcan
                @can('update', $round)
                    <a href="{{ route('badges.collections.edit', $round) }}" class="px-5 py-2.5 bg-white/10 text-white rounded-xl font-bold text-sm">تعديل</a>
                @endcan
                @can('cancel', $round)
                    <form method="POST" action="{{ route('badges.collections.cancel', $round) }}" onsubmit="return confirm('إلغاء هذه الجولة؟')">
                        @csrf
                        <button class="px-5 py-2.5 bg-red-500/80 text-white rounded-xl font-bold text-sm">إلغاء</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    @php
        $totalDistributed = $round->items->sum('distributed_quantity');
        $totalCollected = $round->items->sum('collected_quantity');
        $missingCount = $round->items->where('status', \App\Enums\Badges\CollectionItemStatus::Missing)->count();
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6" dir="rtl">
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">إجمالي الموزّع بالجولة</div><div class="text-2xl font-black">{{ $totalDistributed }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">إجمالي المحصّل</div><div class="text-2xl font-black text-emerald-700">{{ $totalCollected }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">بنود مفقودة</div><div class="text-2xl font-black text-red-600">{{ $missingCount }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">عدد البنود/الطلاب</div><div class="text-2xl font-black">{{ $round->items->count() }}</div></div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto" dir="rtl">
        <table class="w-full text-right">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">الطالب</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">النوع</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">الموزّع</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">المحصّل</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">الحالة</th>
                    @if ($round->isDraft())<th class="px-6 py-4 text-sm font-bold text-gray-600">تحديث</th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($round->items as $item)
                    <tr>
                        <td class="px-6 py-4 text-sm font-bold">{{ $item->student->name }}</td>
                        <td class="px-6 py-4 text-sm">{{ $item->badgeType->name ?? '— لا أوسمة —' }}</td>
                        <td class="px-6 py-4 text-sm font-black">{{ $item->distributed_quantity }}</td>
                        <td class="px-6 py-4 text-sm font-black">{{ $item->collected_quantity }}</td>
                        <td class="px-6 py-4 text-sm">
                            <span class="px-2 py-1 rounded-lg text-xs font-bold {{ match($item->status->value) { 'collected' => 'bg-emerald-50 text-emerald-700', 'partial' => 'bg-amber-50 text-amber-700', 'missing' => 'bg-red-50 text-red-600', default => 'bg-gray-100 text-gray-600' } }}">{{ $item->status->label() }}</span>
                            @if ($item->notes)<div class="text-xs text-gray-400 mt-1">{{ $item->notes }}</div>@endif
                        </td>
                        @if ($round->isDraft())
                            <td class="px-6 py-4 text-sm">
                                @can('update', $item)
                                    <form method="POST" action="{{ route('badges.collections.items.update', $item) }}" class="flex items-center gap-2">
                                        @csrf @method('PUT')
                                        <input type="number" name="collected_quantity" min="0" max="{{ $item->distributed_quantity }}" value="{{ $item->collected_quantity }}" class="w-20 p-1.5 px-2 border border-gray-200 rounded-lg text-sm">
                                        <select name="status" class="p-1.5 px-2 border border-gray-200 rounded-lg text-sm bg-white">
                                            <option value="">تلقائي من الكمية</option>
                                            <option value="missing" @selected($item->status->value === 'missing')>مفقود</option>
                                            <option value="not_collected" @selected($item->status->value === 'not_collected')>لم يُحصَّل</option>
                                        </select>
                                        <input type="text" name="notes" value="{{ $item->notes }}" placeholder="ملاحظة" class="w-28 p-1.5 px-2 border border-gray-200 rounded-lg text-sm">
                                        <button class="px-3 py-1.5 bg-[#0a5c36] text-white rounded-lg text-xs font-bold">حفظ</button>
                                    </form>
                                @endcan
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400 text-sm">لا بنود في هذه الجولة</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.markaz-layout>
