<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6 mb-8">
        <div class="text-right w-full md:w-auto z-10">
            <h1 class="text-3xl font-black mb-2">الاستحقاقات والتوزيعات</h1>
            <p class="text-emerald-100/80 text-sm font-medium">{{ $paginator->total() }} عملية إعطاء</p>
        </div>
        <div class="flex flex-wrap gap-2.5 z-10">
            @can('badges.entitlements.create')
                <a href="{{ route('badges.entitlements.create') }}" class="px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-2xl shadow-lg active:scale-95">+ استحقاق وتوزيع جديد</a>
            @endcan
            <a href="{{ route('badges.entitlements.statistics', array_filter(['month' => request('month'), 'teacher_id' => request('teacher_id'), 'circle_id' => request('circle_id'), 'status' => request('status')])) }}" class="px-5 py-3 bg-white/10 hover:bg-white/20 border border-white/25 text-white font-bold rounded-2xl transition active:scale-95">الإحصائيات</a>
            @can('badges.reports.view')
                <a href="{{ route('badges.entitlements.report', array_filter(['month' => request('month'), 'teacher_id' => request('teacher_id'), 'circle_id' => request('circle_id'), 'status' => request('status'), 'q' => request('q')])) }}" class="px-5 py-3 bg-white/10 hover:bg-white/20 border border-white/25 text-white font-bold rounded-2xl transition active:scale-95">التقرير</a>
            @endcan
        </div>
        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6" dir="rtl">
        <form method="GET" class="space-y-3">
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">بحث باسم الطالب</label>
                <input type="search" name="q" value="{{ request('q') }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
            </div>
            <div class="flex flex-col lg:flex-row lg:flex-wrap gap-3 items-end">
            <div class="w-full lg:flex-1 lg:min-w-[150px]">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">الحلقة</label>
                <select name="circle_id" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    <option value="">كل الحلقات</option>
                    @foreach ($circles as $c)<option value="{{ $c->id }}" @selected(request('circle_id') == $c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="w-full lg:flex-1 lg:min-w-[150px]">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">المسؤول عن التوزيع</label>
                <select name="teacher_id" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    <option value="">كل المسؤولين</option>
                    @foreach ($distributors as $d)<option value="{{ $d->id }}" @selected((string) request('teacher_id') === (string) $d->id)>{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <div class="w-full lg:flex-1 lg:min-w-[150px]">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">الشهر</label>
                <input type="month" name="month" value="{{ request('month') }}" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
            </div>
            <div class="w-full lg:flex-1 lg:min-w-[150px]">
                <label class="block text-xs font-bold text-gray-400 mb-1.5">الحالة</label>
                <select name="status" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    <option value="">الكل</option>
                    @foreach (\App\Enums\Badges\EntitlementStatus::cases() as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>@endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button class="px-6 py-2.5 bg-[#0a5c36] text-white font-bold rounded-xl text-sm">تصفية</button>
                @if (request()->hasAny(['q', 'circle_id', 'teacher_id', 'month', 'status']))
                    <a href="{{ route('badges.entitlements.index') }}" class="px-4 py-2.5 bg-gray-100 text-gray-500 font-bold rounded-xl text-sm">مسح</a>
                @endif
            </div>
            </div>
        </form>
    </div>

    {{-- كل عملية إعطاء (دفعة) في بطاقة: رأس بهوية الطالب وشريط تقدم، والأنواع عند التوسيع --}}
    <div class="space-y-4" dir="rtl">
        @forelse ($groups as $group)
            @php
                $items = $group['items'];
                $first = $items->first();
                $totalQty = $items->sum('quantity');
                $totalRemaining = $items->sum(fn ($e) => $e->remainingQuantity());
                $totalDelivered = $totalQty - $totalRemaining;
                $pct = $totalQty > 0 ? (int) round($totalDelivered / $totalQty * 100) : 0;
                $statusValues = $items->pluck('status')->map(fn ($s) => $s->value);
                $groupStatus = match (true) {
                    $statusValues->every(fn ($v) => $v === 'cancelled') => ['ملغاة', 'bg-red-50 text-red-600'],
                    $statusValues->every(fn ($v) => $v === 'completed') => ['مكتملة', 'bg-emerald-50 text-emerald-700'],
                    $totalDelivered > 0 => ['جزئية', 'bg-amber-50 text-amber-700'],
                    default => ['معلّقة', 'bg-gray-100 text-gray-600'],
                };
            @endphp
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm hover:shadow-md transition overflow-hidden" x-data="{ open: false }">
                <div class="flex items-stretch">
                    <div class="w-1.5 shrink-0 bg-gradient-to-b from-[#0b3d2c] via-emerald-600 to-emerald-400"></div>
                    <div class="flex-1 min-w-0 px-4 sm:px-6 py-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="w-11 h-11 rounded-2xl bg-[#0b3d2c] text-white flex items-center justify-center font-black text-lg shrink-0 shadow">{{ mb_substr($first->student->name, 0, 1) }}</div>
                            <div class="min-w-0">
                                <div class="font-black text-[15px] leading-tight truncate">{{ $first->student->name }}</div>
                                <div class="text-[11px] text-gray-400 mt-0.5 truncate">{{ $first->circle->name }} • الموزّع: {{ $first->teacher->name }} • {{ $first->entitlement_date->format('Y-m-d') }}</div>
                            </div>
                            <span class="text-[11px] font-bold bg-indigo-50 text-indigo-700 px-2 py-1 rounded-lg">{{ $items->count() }} {{ $items->count() === 1 ? 'نوع' : 'أنواع' }}</span>
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $groupStatus[1] }}">{{ $groupStatus[0] }}</span>
                            <button type="button" @click="open = !open" class="mr-auto flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-gray-50 hover:bg-emerald-50 text-[#0a5c36] text-xs font-black transition">
                                <span x-text="open ? 'إخفاء الأنواع' : 'عرض الأنواع'"></span>
                                <svg class="w-3.5 h-3.5 transition-transform duration-300" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                        </div>

                        <div class="mt-4 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-5">
                            <div class="flex-1 min-w-0">
                                <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full bg-gradient-to-l from-emerald-600 to-emerald-400 transition-all duration-500" style="width: {{ $pct }}%"></div>
                                </div>
                                <div class="flex items-center justify-between text-[11px] text-gray-400 mt-1.5">
                                    <span>سُلّم <b class="text-emerald-700">{{ $totalDelivered }}</b> من أصل <b class="text-gray-600">{{ $totalQty }}</b></span>
                                    <span class="font-black {{ $pct === 100 ? 'text-emerald-600' : 'text-gray-500' }}">{{ $pct }}%</span>
                                </div>
                            </div>
                            <div class="flex gap-2 shrink-0">
                                <div class="bg-gray-50 rounded-xl px-3 py-1.5 text-center min-w-[64px]">
                                    <div class="text-sm font-black text-gray-800 leading-none">{{ $totalQty }}</div>
                                    <div class="text-[10px] text-gray-400 mt-1">المستحق</div>
                                </div>
                                <div class="bg-emerald-50 rounded-xl px-3 py-1.5 text-center min-w-[64px]">
                                    <div class="text-sm font-black text-emerald-700 leading-none">{{ $totalDelivered }}</div>
                                    <div class="text-[10px] text-emerald-600/70 mt-1">الموزّع</div>
                                </div>
                                <div class="{{ $totalRemaining > 0 ? 'bg-amber-50' : 'bg-gray-50' }} rounded-xl px-3 py-1.5 text-center min-w-[64px]">
                                    <div class="text-sm font-black {{ $totalRemaining > 0 ? 'text-amber-600' : 'text-gray-400' }} leading-none">{{ $totalRemaining }}</div>
                                    <div class="text-[10px] {{ $totalRemaining > 0 ? 'text-amber-600/70' : 'text-gray-400' }} mt-1">المتبقي</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="open" x-cloak class="border-t border-dashed border-gray-200 bg-gray-50/60 px-4 sm:px-6 py-3 space-y-2">
                    @foreach ($items as $e)
                        @php $remaining = $e->remainingQuantity(); $distributorBalance = $distributorBalances[$e->teacher_id . '_' . $e->badge_type_id] ?? 0; $typePct = $e->quantity > 0 ? (int) round(($e->quantity - $remaining) / $e->quantity * 100) : 0; @endphp
                        <div class="bg-white rounded-2xl border border-gray-100 px-4 py-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                            <span class="text-sm font-black w-full sm:w-44">{{ $e->badgeType->name }}</span>
                            <div class="flex items-center gap-2 flex-1 min-w-[140px]">
                                <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $typePct }}%"></div>
                                </div>
                                <span class="text-[11px] text-gray-400 whitespace-nowrap">{{ $e->quantity - $remaining }}/{{ $e->quantity }}</span>
                            </div>
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
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm px-6 py-10 text-center text-gray-400 text-sm">لا عمليات مطابقة</div>
        @endforelse
    </div>
    <div class="mt-6"><x-pagination :paginator="$paginator" /></div>
</x-layouts.markaz-layout>
