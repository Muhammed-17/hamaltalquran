<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 text-white shadow-xl mb-8 flex flex-col md:flex-row justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black">جولة تحصيل — {{ $round->circle->name }}</h1>
            <p class="text-emerald-100/80 text-sm">المسؤول {{ $round->teacher->name }} — {{ $round->collection_date->format('Y-m-d') }} — الحالة: {{ $round->status->label() }}</p>
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
        $lostQtyTotal = $round->items->where('status', \App\Enums\Badges\CollectionItemStatus::Damaged)->sum('collected_quantity')
            + $round->items->where('status', \App\Enums\Badges\CollectionItemStatus::Missing)->sum(fn ($i) => $i->distributed_quantity - $i->collected_quantity);
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6" dir="rtl">
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">إجمالي الموزّع بالجولة</div><div class="text-2xl font-black">{{ $totalDistributed }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">إجمالي المحصّل</div><div class="text-2xl font-black text-emerald-700">{{ $totalCollected }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">تالف/مفقود (كمية)</div><div class="text-2xl font-black text-orange-600">{{ $lostQtyTotal }}</div></div>
        <div class="bg-white rounded-2xl border shadow-sm p-5"><div class="text-xs font-bold text-gray-400 mb-1">عدد الطلاب</div><div class="text-2xl font-black">{{ $studentGroups->count() }}</div></div>
    </div>

    {{-- مجمّع: بطاقة لكل طالب، وصف واحد لكل نوع بإجمالياته وتعليمه المجمّع --}}
    <div class="space-y-4" dir="rtl">
        @forelse ($studentGroups as $group)
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 flex items-center gap-3 border-b border-gray-50">
                    <span class="w-10 h-10 rounded-full flex items-center justify-center text-white font-black shrink-0" style="background-color:#0b3d2c">{{ mb_substr($group['student']->name, 0, 1) }}</span>
                    <div>
                        <div class="font-black text-gray-800">{{ $group['student']->name }}</div>
                        <div class="text-[11px] text-gray-400">موزّع {{ $group['types']->sum('distributed') }} • سليم {{ $group['types']->sum('good') }} • تالف/مفقود {{ $group['types']->sum('lost') }}</div>
                    </div>
                </div>
                @if ($group['types']->isEmpty())
                    <div class="px-6 py-4 text-sm text-gray-400">لا أوسمة لهذا الطالب في هذه الجولة</div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-right">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-xs font-bold text-gray-500">نوع الوسام</th>
                                <th class="px-6 py-3 text-xs font-bold text-gray-500 text-center">الموزّع</th>
                                <th class="px-6 py-3 text-xs font-bold text-gray-500 text-center">المحصّل</th>
                                <th class="px-6 py-3 text-xs font-bold text-gray-500 text-center">تالف/مفقود</th>
                                <th class="px-6 py-3 text-xs font-bold text-gray-500 text-center">الباقي</th>
                                <th class="px-6 py-3 text-xs font-bold text-gray-500">الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($group['types'] as $row)
                                <tr>
                                    <td class="px-6 py-4 text-sm font-bold">{{ $row['type']->name ?? '— لا أوسمة —' }}</td>
                                    <td class="px-6 py-4 text-sm font-black text-center">{{ $row['distributed'] }}</td>
                                    <td class="px-6 py-4 text-sm font-black text-center text-emerald-700">{{ $row['good'] }}</td>
                                    <td class="px-6 py-4 text-sm font-black text-center text-orange-600">{{ $row['lost'] }}</td>
                                    <td class="px-6 py-4 text-sm font-black text-center text-gray-500">{{ $row['pending'] }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="flex flex-wrap gap-1">
                                            @if ($row['good'] > 0)<span class="px-2 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-700">مُحصَّل {{ $row['good'] }}</span>@endif
                                            @if ($row['lost'] > 0)<span class="px-2 py-1 rounded-lg text-xs font-bold bg-orange-50 text-orange-600">تالف/مفقود {{ $row['lost'] }}</span>@endif
                                            @if ($row['pending'] > 0)<span class="px-2 py-1 rounded-lg text-xs font-bold bg-gray-100 text-gray-600">متبقٍّ {{ $row['pending'] }}</span>@endif
                                        </span>
                                        @if ($row['notes'])<div class="text-xs text-gray-400 mt-1">{{ $row['notes'] }}</div>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-6 py-10 text-center text-gray-400 text-sm">لا بنود في هذه الجولة</div>
        @endforelse
    </div>

    @if (($owedDeductions ?? collect())->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto mt-6" dir="rtl">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="font-black text-gray-800">مستحق مُخصم بالاستبدال بلعبة ضمن هذه الجولة</h3>
                <p class="text-[11px] text-gray-400 mt-0.5">أوسمة مستحقة غير مسلّمة خُصمت من حساب الطلاب عند إنشاء الجولة (أُقفل عجزها) — تُعاد عند إلغاء الجولة وهي مسودة.</p>
            </div>
            <table class="w-full text-right">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-sm font-bold text-gray-600">الطالب</th>
                        <th class="px-6 py-3 text-sm font-bold text-gray-600">نوع الوسام</th>
                        <th class="px-6 py-3 text-sm font-bold text-gray-600 text-center">المخصوم من المستحق</th>
                        <th class="px-6 py-3 text-sm font-bold text-gray-600">الحالة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($owedDeductions as $d)
                        <tr>
                            <td class="px-6 py-3 text-sm font-bold">{{ $d['student'] }}</td>
                            <td class="px-6 py-3 text-sm">{{ $d['type'] }}</td>
                            <td class="px-6 py-3 text-sm text-center font-black" style="color:#b45309">{{ $d['qty'] }}</td>
                            <td class="px-6 py-3 text-sm">{{ $d['cancelled'] ? 'ملغي (أُعيد)' : 'مُخصم' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.markaz-layout>
