<div class="space-y-6" x-data="formData()">
    <!-- Header -->
    <div class="bg-[#0b3d2c] rounded-3xl p-8 text-white flex justify-between items-center shadow-xl">
        <div>
            <h1 class="text-3xl font-black mb-2">
                {{ isset($subscription) ? 'تعديل الاشتراك' : 'تسجيل اشتراك جديد' }}
            </h1>
            <p>إضافة سجل سداد مالي لطالب في المركز</p>
        </div>

        <a href="{{ route('subscriptions.index') }}"
            class="px-6 py-3 bg-white/10 hover:bg-white/20 border border-white/20 rounded-2xl font-bold transition">
            العودة للإحصائيات
        </a>
    </div>

    {{-- ✅ أضف التنبيه هنا --}}
    @if($isLockedByConfirmedRound ?? false)
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 flex items-start gap-4 shadow-sm">
        <div class="bg-amber-100 p-3 rounded-full text-amber-600 shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>
        <div>
            <h3 class="text-base font-bold text-amber-800 mb-1">
                هذا الاشتراك جزء من تحصيل مؤكَّد
            </h3>
            <p class="text-sm text-amber-700 leading-relaxed">
                الحقول المالية (المبلغ، الحالة، المحصّل) محمية ولا يمكن تعديلها مباشرة.
                أي تغيير في المبلغ سيتم تسجيله كطلب تسوية للمراجعة من الإدارة.
            </p>
        </div>
    </div>
    @endif

    <!-- Form -->
    <div class="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">

        <form action="{{ isset($subscription) ? route('subscriptions.update', $subscription) : route('subscriptions.store') }}"
            method="POST" @submit="isSubmitting = true" class="max-w-4xl mx-auto space-y-8">
            @csrf
            @if(isset($subscription))
            @method('PUT')
            @endif
            <!-- Selected Student Summary -->
            <div x-show="selectedStudent || '{{ request('student_id') }}'"
                class="col-span-1 md:col-span-2 bg-emerald-50 border border-emerald-100 rounded-2xl p-4 flex items-center gap-4 animate-fade-in">
                <div class="bg-emerald-100 p-3 rounded-full text-emerald-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">
                        يتم تسجيل الاشتراك للطالب في حلقة:
                        <span class="font-bold text-emerald-700"
                            x-text="circles.find(c => c.id == selectedCircle)?.name"></span>
                    </p>
                    <p class="text-lg font-black text-gray-800"
                        x-text="students.find(s => s.id == selectedStudent)?.name"></p>
                </div>
            </div>
            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- ✅ حقل المعلم - في بداية الـ Form --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            @php
            $isAdmin = auth()->user()->hasRole(['admin', 'general_manager']);
            $currentUser = auth()->user();
            @endphp

            {{-- ✅ للأدمن: قائمة منسدلة بجميع المشرفين والمدراء والمعلمين --}}
            @if($isAdmin)
            <div>
                <label class="block text-gray-700 font-bold mb-2">المعلم / المسؤول</label>
                @php
                $teacherOptions = $teachers->map(fn($t) => [
                'value' => $t->id,
                'label' => $t->name . ($t->roles->isNotEmpty()
                ? ' (' . $t->roles->pluck('name')->map(fn($r) => ['supervisor'=>'مشرف','manager'=>'مدير','general_manager'=>'مدير عام','teacher'=>'معلم'][$r] ?? $r)->join('، ') . ')'
                : '')
                ])->values()->toArray();
                @endphp
                <x-searchable-select
                    name="teacher_id"
                    placeholder="اختر المعلم..."
                    search-placeholder="ابحث عن معلم..."
                    :options="$teacherOptions"
                    :default-value="old('teacher_id', $subscription->teacher_id ?? '')" />
                <p class="text-xs text-gray-400 mt-1">يتم تسجيل الاشتراك باسم المعلم المختار...</p>
                <x-input-error :messages="$errors->get('teacher_id')" class="mt-2" />
            </div>
            @else
            <div class="bg-gray-50 border border-gray-100 rounded-2xl p-4 flex items-center gap-3">
                <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-bold">المعلم / المسؤول</p>
                    <p class="font-black text-gray-800">{{ $currentUser->name }}</p>
                </div>
            </div>
            <input type="hidden" name="teacher_id" value="{{ $subscription->teacher_id ?? $currentUser->id }}">
            @endif

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- ✅ حقل المحصِّل — خارج شرط الأدمن، يظهر للجميع --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div x-show="status === 'مدفوع'">
                @if($collectedByUsers->isNotEmpty())
                <label class="block text-gray-700 font-bold mb-2">المحصِّل (من قبض المبلغ فعلياً)</label>
                @php
                $collectedByOptions = $collectedByUsers->map(fn($u) => [
                'value' => $u->id,
                'label' => $u->name,
                ])->values()->toArray();
                @endphp
                <x-searchable-select
                    name="collected_by"
                    placeholder="اختر المحصِّل..."
                    search-placeholder="ابحث عن محصِّل..."
                    :options="$collectedByOptions"
                    :default-value="old('collected_by', $subscription->collected_by ?? $currentUser->id)" />
                <p class="text-xs text-gray-400 mt-1">الشخص الذي استلم المبلغ فعلياً — قد يختلف عن المعلم المسجِّل.</p>
                <x-input-error :messages="$errors->get('collected_by')" class="mt-2" />
                @else
                <input type="hidden" name="collected_by" value="{{ $subscription->collected_by ?? $currentUser->id }}">
                @endif
            </div>
            <div class="grid md:grid-cols-2 gap-8 col-span-1 md:col-span-2">

                {{-- Circle --}}
                <div>
                    <label class="block text-gray-700 font-bold mb-2">الحلقة</label>

                    @php
                    $circleOptions = $circles->map(fn($c) => [
                    'value' => $c->id,
                    'label' => $c->name,
                    ])->values()->toArray();
                    @endphp

                    {{-- Circle --}}
                    <x-searchable-select
                        name="circle_id"
                        placeholder="اختر الحلقة..."
                        search-placeholder="ابحث عن حلقة..."
                        :options="$circleOptions"
                        default-value="" />
                    <x-input-error :messages="$errors->get('circle_id')" class="mt-2" />
                </div>


                {{-- Student --}}
                <div>
                    <label class="block text-gray-700 font-bold mb-2">الطالب</label>

                    <x-searchable-select
                        name="student_id"
                        placeholder="اختر الطالب..."
                        search-placeholder="ابحث عن طالب..."
                        :options="[]"
                        default-value="" />

                    <p x-show="selectedCircle && filteredStudents.length === 0"
                        class="text-emerald-600 text-sm font-bold mt-2 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        كل الطلاب المقيدين في هذه الحلقة لديهم اشتراك مسجَّل لهذا الشهر بالفعل
                    </p>

                    {{-- ✅ جديد: تنبيه الطالب المعفي --}}
                    <div x-show="isSelectedStudentExempted"
                        x-transition
                        class="mt-3 bg-amber-50 border border-amber-200 rounded-xl p-4 text-amber-700 text-sm font-bold flex items-start gap-2">
                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>⚠️ هذا الطالب مُسجَّل كمعفي من الاشتراك هذا الشهر. تسجيل دفعة له يُعتبر استثناءً، وسيتم الاحتفاظ بسجل الإعفاء لباقي الأشهر ما لم تُعدِّل ذلك يدويًا.</span>
                    </div>

                    <x-input-error :messages="$errors->get('student_id')" class="mt-2" />
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-8">
                <!-- Month -->
                <div>
                    <label class="block text-gray-700 font-bold mb-2">اشتراك شهر</label>

                    <input type="month" name="month" x-model="selectedMonth" class="w-full bg-gray-50 border-none rounded-2xl p-4 focus:ring-2 focus:ring-emerald-500 font-bold">

                    <x-input-error :messages="$errors->get('month')" class="mt-2" />

                </div>

                {{-- ─── المبلغ (نسخة واحدة فقط) ─────────────────────── --}}
                <div>
                    <label class="block text-gray-700 font-bold mb-2">المبلغ</label>

                    <div class="relative">
                        {{--
                            عنصر واحد فقط مفعّل (enabled) في كل لحظة، فيُرسل اسم
                            "amount" مرة واحدة فقط مهما كانت حالة "status".
                            - لما الحالة ليست "معفي": الحقل الرقمي مفعّل ويرسل القيمة الحقيقية.
                            - لما الحالة "معفي": الحقل الرقمي مُعطّل (disabled لا يُرسل قيمته)،
                              ويتولى الحقل المخفي إرسال 0 بدلاً منه.
                        --}}
                        <input type="number" min="0" step="0.01" name="amount" x-model="amount"
                            :disabled="status === 'معفي'"
                            class="w-full bg-gray-50 border-none rounded-2xl p-4 focus:ring-2 focus:ring-emerald-500 font-black text-emerald-600 text-xl disabled:opacity-50 disabled:cursor-not-allowed">

                        <input type="hidden" name="amount" value="0" :disabled="status !== 'معفي'">

                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                            ج.م
                        </span>

                    </div>

                    <p x-show="status === 'معفي'" class="text-xs text-gray-400 mt-1">
                        الطالب معفي من الاشتراك — لا قيمة مطلوبة
                    </p>

                    <x-input-error :messages="$errors->get('amount')" class="mt-2" />

                </div>

                <!-- Payment -->
                <div x-show="status !== 'معفي'">
                    <label class="block text-gray-700 font-bold mb-2">طريقة الدفع</label>

                    <select name="payment_method" :disabled="status === 'معفي'"
                        class="w-full bg-gray-50 border-none rounded-2xl p-4 focus:ring-2 focus:ring-emerald-500 font-bold">

                        @php $paymentMethod = old('payment_method'); @endphp

                        <option value="نقدي" {{ $paymentMethod == 'نقدي' ? 'selected' : '' }}>نقدي</option>
                        <option value="تحويل بنكي" {{ $paymentMethod == 'تحويل بنكي' ? 'selected' : '' }}>تحويل بنكي</option>
                        <option value="أخرى" {{ $paymentMethod == 'أخرى' ? 'selected' : '' }}>أخرى</option>

                    </select>

                </div>

                <!-- Status -->
                <div>
                    <label class="block text-gray-700 font-bold mb-2">حالة السداد</label>

                    <select name="status" x-model="status"
                        class="w-full bg-gray-50 border-none rounded-2xl p-4 focus:ring-2 focus:ring-emerald-500 font-bold">

                        <option value="مدفوع">مدفوع</option>
                        <option value="معفي">معفي</option>

                    </select>

                </div>
            </div>


            <!-- Notes -->
            <div>
                <label class="block text-gray-700 font-bold mb-2">ملاحظات</label>

                <textarea name="notes" rows="3"
                    class="w-full bg-gray-50 border-none rounded-3xl p-6 focus:ring-2 focus:ring-emerald-500">{{ old('notes') }}</textarea>

            </div>


            <!-- Submit -->
            <button type="submit" :disabled="isSubmitting"
                class="w-full bg-[#0a5c36] text-white rounded-4xl p-6
font-black text-xl
hover:scale-[1.01]
active:scale-95
transition-all
shadow-2xl
flex items-center justify-center gap-4
disabled:opacity-60">

                <svg x-show="isSubmitting" class="animate-spin h-6 w-6" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"
                        fill="none" />
                </svg>

                <span x-text="isSubmitting ? 'جاري الحفظ...' : '{{ isset($subscription) ? 'تحديث الاشتراك' : 'تأكيد وتسجيل الاشتراك' }}'"></span>
            </button>

        </form>
    </div>

</div>