<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6 mb-8">
        <div class="text-right w-full md:w-auto z-10">
            <h1 class="text-3xl font-black mb-2">الاستحقاقات والتوزيعات</h1>
            <p class="text-emerald-100/80 text-sm font-medium">{{ $paginator->total() }} عملية إعطاء</p>
        </div>
        @can('badges.entitlements.create')
            <a href="{{ route('badges.entitlements.create') }}" class="px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-2xl shadow-lg active:scale-95">+ استحقاق وتوزيع جديد</a>
        @endcan
        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6" dir="rtl">
        <form method="GET" class="flex flex-col lg:flex-row gap-4 items-end">
            <div class="w-full lg:flex-1">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">بحث باسم الطالب</label>
                <input type="search" name="q" value="{{ request('q') }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
            </div>
            <div class="w-full lg:w-52">
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
                    @foreach (\App\Enums\Badges\EntitlementStatus::cases() as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>@endforeach
                </select>
            </div>
            <button class="px-6 py-2.5 bg-[#0a5c36] text-white font-bold rounded-xl text-sm">تصفية</button>
        </form>
    </div>

    {{-- كل عملية إعطاء (دفعة) في بطاقة واحدة، والأنواع داخلها عند التوسيع --}}
    <div class="space-y-3" dir="rtl">
        @forelse ($groups as $group)
            @php
                $items = $group['items'];
                $first = $items->first();
                $totalQty = $items->sum('quantity');
                $totalRemaining = $items->sum(fn ($e) => $e->remainingQuantity());
                $totalDelivered = $totalQty - $totalRemaining;
                $statusValues = $items->pluck('status')->map(fn ($s) => $s->value);
                $groupStatus = match (true) {
                    $statusValues->every(fn ($v) => $v === 'cancelled') => ['ملغاة', 'bg-red-50 text-red-600'],
                    $statusValues->every(fn ($v) => $v === 'completed') => ['مكتملة', 'bg-emerald-50 text-emerald-700'],
                    $totalDelivered > 0 => ['جزئية', 'bg-amber-50 text-amber-700'],
                    default => ['معلّقة', 'bg-gray-100 text-gray-600'],
                };
            @endphp
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" x-data="{ open: false }">
                <button type="button" @click="open = !open" class="w-full flex flex-wrap items-center gap-x-4 gap-y-1.5 px-4 sm:px-6 py-4 text-right hover:bg-gray-50 transition">
                    <span class="font-black text-sm">{{ $first->student->name }}</span>
                    <span class="text-xs text-gray-500">{{ $first->circle->name }} • الموزّع: {{ $first->teacher->name }}</span>
                    <span class="text-[11px] font-bold bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-lg">{{ $items->count() }} {{ $items->count() === 1 ? 'نوع' : 'أنواع' }}</span>
                    <span class="text-xs text-gray-500">المستحق <b class="text-gray-800">{{ $totalQty }}</b> • الموزّع <b class="text-emerald-700">{{ $totalDelivered }}</b> • المتبقي <b class="{{ $totalRemaining > 0 ? 'text-amber-600' : 'text-gray-400' }}">{{ $totalRemaining }}</b></span>
                    <span class="text-xs text-gray-400">{{ $first->entitlement_date->format('Y-m-d') }}</span>
                    <span class="px-2 py-1 rounded-lg text-xs font-bold {{ $groupStatus[1] }}">{{ $groupStatus[0] }}</span>
                    <span class="mr-auto text-xs font-bold text-[#0a5c36]" x-text="open ? 'إخفاء الأنواع' : 'عرض الأنواع'"></span>
                </button>
                <div x-show="open" x-cloak class="border-t border-gray-100 divide-y divide-gray-50 bg-gray-50/40">
                    @foreach ($items as $e)
                        @php $remaining = $e->remainingQuantity(); $distributorBalance = $distributorBalances[$e->teacher_id . '_' . $e->badge_type_id] ?? 0; @endphp
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 sm:px-6 py-3">
                            <span class="text-sm font-bold w-full sm:w-40">{{ $e->badgeType->name }}</span>
                            <span class="text-xs text-gray-500">المستحق <b class="text-gray-800">{{ $e->quantity }}</b></span>
                            <span class="text-xs text-gray-500">الموزّع <b class="text-emerald-700">{{ $e->quantity - $remaining }}</b></span>
                            <span class="text-xs text-gray-500">المتبقي <b class="{{ $remaining > 0 ? 'text-amber-600' : 'text-gray-400' }}">{{ $remaining }}</b></span>
                            <span class="px-2 py-1 rounded-lg text-xs font-bold {{ match($e->status->value) { 'completed' => 'bg-emerald-50 text-emerald-700', 'partial' => 'bg-amber-50 text-amber-700', 'cancelled' => 'bg-red-50 text-red-600', default => 'bg-gray-100 text-gray-600' } }}">{{ $e->status->label() }}</span>
                            <a href="{{ route('badges.entitlements.show', $e) }}" class="text-[#0a5c36] text-sm font-bold hover:underline">عرض</a>
                            @if ($remaining > 0 && $e->status->value !== 'cancelled')
                                @can('badges.distributions.create')
                                    @if ($distributorBalance > 0)
                                        <form method="POST" action="{{ route('badges.distributions.store') }}" class="flex items-center gap-1.5">
                                            @csrf
                                            <input type="hidden" name="entitlement_id" value="{{ $e->id }}">
                                            <input type="hidden" name="distribution_date" value="{{ date('Y-m-d') }}">
                                            <input type="number" name="quantity" min="1" max="{{ $remaining }}" value="{{ min($remaining, $distributorBalance) }}" class="w-16 p-1.5 border border-gray-200 rounded-lg text-xs text-center font-bold bg-white">
                                            <button class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg whitespace-nowrap">توزيع المتبقي</button>
                                        </form>
                                        <span class="text-[11px] text-gray-400">رصيد الموزّع: {{ $distributorBalance }}</span>
                                    @else
                                        <span class="text-[11px] text-gray-400">لا رصيد لدى الموزّع لاستكمال التوزيع</span>
                                    @endif
                                @endcan
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-6 py-10 text-center text-gray-400 text-sm">لا عمليات مطابقة</div>
        @endforelse
    </div>
    <div class="mt-6"><x-pagination :paginator="$paginator" /></div>
</x-layouts.markaz-layout>
