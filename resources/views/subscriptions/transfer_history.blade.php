<x-layouts.markaz-layout>
    <div class="max-w-5xl mx-auto py-8">

        {{-- ─── Header ────────────────────────────────────────────── --}}
        <div class="bg-[#0b3d2c] rounded-3xl p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6 mb-8">
            <div class="text-right w-full md:w-auto z-10 wrap-break-word">
                <h1 class="text-3xl font-black mb-2">سجل تحويلات الأموال</h1>
                <p class="text-emerald-100/80 text-sm font-medium">سجل كامل لكل عمليات تحويل الاشتراكات بين المحصِّلين</p>
            </div>
            <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                <a href="{{ route('subscription-transfers.index') }}"
                    class="w-full md:w-auto px-6 py-3 bg-white/10 hover:bg-white/20 text-white font-bold rounded-2xl flex items-center justify-center gap-2 transition-all border border-white/20 active:scale-95">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    رجوع لصفحة التحويل
                </a>
            </div>
        </div>

        {{-- ─── Filters ───────────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 mb-6">
            <form method="GET" action="{{ route('subscription-transfers.history') }}" class="space-y-4">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- ✅ بحث بالاسم: من / إلى / بواسطة --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">بحث عن معلم (من / إلى / بواسطة)</label>
                        <input type="text" name="search" value="{{ $search }}"
                            placeholder="اكتب اسم المعلم..."
                            class="w-full rounded-xl border-gray-200 focus:border-[#0b3d2c] focus:ring-[#0b3d2c] text-sm h-11">
                    </div>

                    {{-- الحلقة --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">الحلقة</label>
                        <x-searchable-select
                            name="circle_id"
                            placeholder="جميع الحلقات"
                            search-placeholder="ابحث عن حلقة..."
                            default-option="جميع الحلقات"
                            default-value="{{ $circleId }}"
                            :options="$circles->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values()" />
                    </div>

                    {{-- الشهر --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">الشهر</label>
                        <input type="month" name="month" value="{{ $month }}"
                            class="w-full rounded-xl border-gray-200 focus:border-[#0b3d2c] focus:ring-[#0b3d2c] text-sm h-11">
                    </div>
                </div>

                <div class="flex gap-2 justify-end">
                    @if($hasActiveFilters)
                    <a href="{{ route('subscription-transfers.history') }}"
                        class="bg-gray-100 text-gray-600 px-5 h-11 rounded-xl font-bold hover:bg-gray-200 transition flex items-center gap-2 text-sm">
                        إعادة تعيين
                    </a>
                    @endif
                    <button type="submit"
                        class="bg-[#0b3d2c] text-white px-6 h-11 rounded-xl font-bold hover:bg-[#0a3324] shadow-sm transition flex items-center gap-2 text-sm">
                        🔍 تطبيق التصفية
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-x-auto">
            <table class="w-full text-right text-sm min-w-175">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="p-4">التاريخ</th>
                        <th class="p-4">من</th>
                        <th class="p-4">إلى</th>
                        <th class="p-4">عدد الاشتراكات</th>
                        <th class="p-4">الحلقة</th>
                        <th class="p-4">الشهر</th>
                        <th class="p-4">بواسطة</th>
                        <th class="p-4">ملاحظات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($transfers as $transfer)
                    <tr class="hover:bg-gray-50/50">
                        <td class="p-4 text-gray-500">{{ $transfer->created_at->format('Y-m-d H:i') }}</td>
                        <td class="p-4 font-bold">{{ $transfer->fromUser?->name ?? '—' }}</td>
                        <td class="p-4 font-bold text-emerald-700">{{ $transfer->toUser?->name ?? '—' }}</td>
                        <td class="p-4">{{ $transfer->subscriptions_count }}</td>
                        <td class="p-4 text-gray-600">{{ $transfer->circle?->name ?? '—' }}</td>
                        <td class="p-4 text-gray-600">{{ $transfer->month?->translatedFormat('F Y') ?? '—' }}</td>
                        <td class="p-4 text-gray-500">{{ $transfer->performedBy?->name ?? '—' }}</td>
                        <td class="p-4 text-gray-400">{{ $transfer->notes ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="p-8 text-center text-gray-400">لا توجد عمليات تحويل مطابقة لهذا البحث</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <x-pagination :paginator="$transfers" />
        </div>
    </div>
</x-layouts.markaz-layout>