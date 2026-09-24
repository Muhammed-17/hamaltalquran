<x-layouts.markaz-layout>
    <div class="max-w-5xl mx-auto py-8" x-data="transferPage()" x-init="init()">

        {{-- ─── Header ────────────────────────────────────────────── --}}
        <div class="bg-[#0b3d2c] rounded-3xl p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6 mb-8">
            <div class="text-right w-full md:w-auto z-10 wrap-break-word">
                <h1 class="text-3xl font-black mb-2">تحويل الأموال</h1>
                <p class="text-emerald-100/80 text-sm font-medium">نقل اشتراكات محصَّلة من معلم إلى معلم آخر</p>
            </div>
            <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                {{-- ✅ زر الرجوع لصفحة الاشتراكات --}}
                <a href="{{ route('subscriptions.index') }}"
                    class="w-full md:w-auto px-6 py-3 bg-white/10 hover:bg-white/20 text-white font-bold rounded-2xl flex items-center justify-center gap-2 transition-all border border-white/20 active:scale-95">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    رجوع للاشتراكات
                </a>

                {{-- زر سجل التحويلات --}}
                <a href="{{ route('subscription-transfers.history') }}"
                    class="w-full md:w-auto px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-2xl flex items-center justify-center gap-2 transition-all shadow-lg hover:shadow-emerald-500/20 active:scale-95">
                    📜 سجل التحويلات
                </a>
            </div>
        </div>

        {{-- ─── المشرف على هذه العملية ─────────────────────────── --}}
        @if($performers->isNotEmpty())
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 mb-6">
            <label class="block text-sm font-bold text-gray-700 mb-2">المشرف على هذه العملية</label>
            <x-searchable-select
                name="performed_by"
                placeholder="اختر المشرف... (افتراضيًا حسابك الحالي)"
                search-placeholder="ابحث عن مشرف أو مدير..."
                :options="$performers->map(fn($u) => ['value' => $u->id, 'label' => $u->name])->values()"
                default-value="" />
            <p class="text-xs text-gray-400 mt-1">اتركه فارغًا لتسجيل العملية باسم حسابك الحالي.</p>
        </div>
        @endif

        {{-- ─── فلاتر الاختيار ─────────────────────────────────── --}}
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 space-y-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- الحلقة --}}
                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">الحلقة</label>
                    <x-searchable-select
                        name="circle_id"
                        placeholder="اختر الحلقة..."
                        search-placeholder="ابحث عن حلقة..."
                        :options="$circles->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values()"
                        default-value="" />

                </div>

                {{-- الشهر --}}
                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">الشهر</label>
                    <input type="month" x-model="filters.month"
                        class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all">
                </div>

                {{-- المحصِّل الحالي --}}
                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">من (المحصِّل الحالي)</label>
                    <x-searchable-select
                        name="collected_by"
                        placeholder="اختر المحصِّل..."
                        search-placeholder="ابحث عن محصِّل..."
                        :options="$collectedByUsers->map(fn($u) => ['value' => $u->id, 'label' => $u->name])->values()"
                        default-value="" />

                </div>
            </div>

            <button type="button" @click="searchSubscriptions()"
                :disabled="!filters.circle_id || !filters.month || !filters.collected_by"
                class="px-6 py-3 bg-[#0a5c36] hover:bg-[#084d2d] disabled:opacity-40 disabled:cursor-not-allowed text-white font-black rounded-2xl text-sm transition-all">
                🔍 عرض الطلاب
            </button>
        </div>

        {{-- ─── نتائج البحث ─────────────────────────────────────── --}}
        <div x-show="searched" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 space-y-6">

            <div x-show="isLoading" class="text-center py-12">
                <svg class="animate-spin w-8 h-8 text-[#0a5c36] mx-auto" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>

            <div x-show="!isLoading && results.length === 0" class="text-center py-12 text-gray-400">
                لا توجد اشتراكات مطابقة لهذا الاختيار
            </div>

            <template x-if="!isLoading && results.length > 0">
                <div class="space-y-4">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <h2 class="text-lg font-bold text-gray-900">الطلاب المطابقون</h2>
                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="text-sm text-gray-500">
                                المحدد: <span x-text="selectedIds.length" class="font-bold text-[#0a5c36]"></span>
                                من <span x-text="results.length"></span>
                            </span>

                            <button type="button" @click="selectAll()"
                                class="text-sm text-[#0a5c36] font-bold hover:underline">تحديد الكل</button>

                            <button type="button" @click="deselectAll()"
                                class="text-sm text-gray-500 font-bold hover:underline">إلغاء التحديد</button>

                            <span class="text-gray-300">|</span>

                            {{-- ✅ تحديد المدفوعين فقط --}}
                            <button type="button" @click="selectByStatus('مدفوع')"
                                x-show="paidCount > 0"
                                class="text-sm text-emerald-700 font-bold hover:underline flex items-center gap-1">
                                تحديد المدفوعين
                                <span class="bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-md text-xs" x-text="paidCount"></span>
                            </button>

                            {{-- ✅ تحديد المعفيين فقط --}}
                            <button type="button" @click="selectByStatus('معفي')"
                                x-show="exemptCount > 0"
                                class="text-sm text-blue-700 font-bold hover:underline flex items-center gap-1">
                                تحديد المعفيين
                                <span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-md text-xs" x-text="exemptCount"></span>
                            </button>

                            {{-- ✅ عرض عدد المحمي — معلوماتي فقط، غير قابل للتحديد --}}
                            <span x-show="lockedCount > 0"
                                title="اشتراكات محمية بجولة تحصيل مؤكَّدة — لا يمكن تحويلها"
                                class="text-sm text-gray-400 font-bold flex items-center gap-1 cursor-help">
                                🔒 محمي
                                <span class="bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-md text-xs" x-text="lockedCount"></span>
                            </span>
                        </div>
                    </div>

                    <div class="border border-gray-100 rounded-2xl overflow-hidden">
                        <table class="w-full text-right text-sm">
                            <thead class="bg-gray-50 text-gray-500">
                                <tr>
                                    <th class="p-3 w-10"></th>
                                    <th class="p-3">الطالب</th>
                                    <th class="p-3">الحالة</th>
                                    <th class="p-3">المبلغ</th>
                                    <th class="p-3">تاريخ الدفع</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <template x-for="sub in results" :key="sub.id">
                                    <tr @click="sub.transferable && toggleSelect(sub.id)"
                                        class="hover:bg-gray-50/50"
                                        :class="{
                                            'cursor-pointer': sub.transferable,
                                            'cursor-not-allowed opacity-60': !sub.transferable,
                                            'bg-emerald-50/50': isSelected(sub.id)
                                        }">
                                        <td class="p-3">
                                            <template x-if="sub.transferable">
                                                <div :class="isSelected(sub.id) ? 'bg-[#0a5c36] border-[#0a5c36]' : 'border-gray-300'"
                                                    class="w-5 h-5 rounded border-2 flex items-center justify-center">
                                                    <svg x-show="isSelected(sub.id)" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </div>
                                            </template>
                                            <template x-if="!sub.transferable">
                                                <span class="text-gray-300" title="لا يمكن التحويل — الاشتراك محمي بجولة تحصيل مؤكَّدة">🔒</span>
                                            </template>
                                        </td>
                                        <td class="p-3 font-bold text-gray-800" x-text="sub.student_name"></td>
                                        <td class="p-3">
                                            <span class="px-2 py-1 rounded-md text-xs font-bold"
                                                :class="sub.status === 'مدفوع' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700'"
                                                x-text="sub.status"></span>
                                        </td>
                                        <td class="p-3 text-gray-600" x-text="sub.amount.toFixed(2) + ' ج.م'"></td>
                                        <td class="p-3 text-gray-400" x-text="sub.paid_at ?? '—'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- ─── إتمام التحويل ─────────────────────────── --}}
                    <div x-show="selectedIds.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-100">
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-gray-700">تحويل إلى</label>
                            <x-searchable-select
                                name="to_user_id"
                                placeholder="اختر المحصِّل الجديد..."
                                search-placeholder="ابحث..."
                                :options="$collectedByUsers->map(fn($u) => ['value' => $u->id, 'label' => $u->name])->values()"
                                default-value="" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-gray-700">ملاحظات (اختياري)</label>
                            <input type="text" x-model="notes"
                                class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm">
                        </div>
                    </div>

                    <button type="button" @click="confirmTransfer()" x-show="selectedIds.length > 0"
                        :disabled="!toUserId || isSubmitting"
                        class="px-6 py-3 bg-[#0a5c36] hover:bg-[#084d2d] disabled:opacity-40 text-white font-black rounded-2xl text-sm transition-all">
                        <span x-text="isSubmitting ? 'جارٍ التحويل...' : `تحويل الاشتراكات المحددة (${selectedIds.length})`"></span>
                    </button>
                </div>
            </template>
        </div>
    </div>

    <script>
    function transferPage() {
        return {
            filters: {
                circle_id: '',
                month: '{{ now()->format('Y-m') }}',
                collected_by: ''
            },
            results: [],
            selectedIds: [],
            toUserId: '',
            performedBy: '',
            notes: '',
            searched: false,
            isLoading: false,
            isSubmitting: false,

            // ✅ عدّادات لكل حالة — تُستخدم في أزرار "تحديد المدفوعين/المعفيين" وعرض عدد المحمي
            get paidCount() {
                return this.results.filter(s => s.status === 'مدفوع' && s.transferable).length;
            },
            get exemptCount() {
                return this.results.filter(s => s.status === 'معفي' && s.transferable).length;
            },
            get lockedCount() {
                return this.results.filter(s => s.locked).length;
            },

            init() {
                // ✅ استمع لتغييرات x-searchable-select عبر حدث searchable-change
                window.addEventListener('searchable-change', (e) => {
                    if (e.detail.name === 'circle_id') this.filters.circle_id = e.detail.value;
                    if (e.detail.name === 'collected_by') this.filters.collected_by = e.detail.value;
                    if (e.detail.name === 'to_user_id') this.toUserId = e.detail.value;
                    if (e.detail.name === 'performed_by') this.performedBy = e.detail.value;
                });
            },

            isSelected(id) {
                return this.selectedIds.includes(id);
            },

            toggleSelect(id) {
                if (this.isSelected(id)) {
                    this.selectedIds = this.selectedIds.filter(x => x !== id);
                } else {
                    this.selectedIds.push(id);
                }
            },

            selectAll() {
                this.selectedIds = this.results.filter(s => s.transferable).map(s => s.id);
            },

            // ✅ تحديد كل الاشتراكات بحالة معينة (مدفوع / معفي) القابلة للتحويل فقط
            selectByStatus(status) {
                this.selectedIds = this.results
                    .filter(s => s.status === status && s.transferable)
                    .map(s => s.id);
            },

            deselectAll() {
                this.selectedIds = [];
            },

            async searchSubscriptions() {
                this.isLoading = true;
                this.searched = true;
                this.selectedIds = [];
                this.toUserId = '';

                const params = new URLSearchParams({
                    circle_id: this.filters.circle_id,
                    month: this.filters.month,
                    collected_by: this.filters.collected_by,
                });

                try {
                    const res = await fetch(`{{ route('subscription-transfers.search') }}?${params}`, {
                        headers: {
                            'Accept': 'application/json'
                        },
                    });
                    const data = await res.json();
                    this.results = data.subscriptions ?? [];
                } catch (e) {
                    showError('تعذر جلب الاشتراكات');
                } finally {
                    this.isLoading = false;
                }
            },

            async confirmTransfer() {
                this.isSubmitting = true;
                try {
                    const res = await fetch('{{ route('subscription-transfers.transfer') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            circle_id: this.filters.circle_id,
                            month: this.filters.month,
                            from_user_id: this.filters.collected_by,
                            to_user_id: this.toUserId,
                            performed_by: this.performedBy,
                            subscription_ids: this.selectedIds,
                            notes: this.notes,
                        }),
                    });
                    const data = await res.json();

                    // ✅ 422 = فشل validation — رد Laravel القياسي فيه data.errors (object لكل حقل)
                    // نعرض أول رسالة موجودة بدل data.message العام (اللي مش موجود أصلاً في رد الـ validate() القياسي)
                    if (res.status === 422) {
                        const firstError = data.errors
                            ? Object.values(data.errors)[0]?.[0]
                            : null;
                        showError(firstError || data.message || 'يرجى التحقق من البيانات المدخلة.');
                        return;
                    }

                    if (!res.ok || !data.success) {
                        showError(data.message || 'حدث خطأ أثناء التحويل');
                        return;
                    }

                    setTimeout(() => {
                        window.location.href = '{{ route('subscriptions.index') }}?transfer_success=' + encodeURIComponent(data.message);
                    }, 300);
                    return;
                } catch (e) {
                    showError('حدث خطأ في الاتصال بالسيرفر');
                } finally {
                    this.isSubmitting = false;
                }
            },
        };
    }
</script>
</x-layouts.markaz-layout>