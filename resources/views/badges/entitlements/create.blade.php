<x-layouts.markaz-layout>
    <div class="max-w-3xl mx-auto px-3 sm:px-0" dir="rtl" x-data="{
        circleId: '{{ old('circle_id') }}',
        studentId: '{{ old('student_id') }}',
        teacherId: '{{ old('teacher_id') }}',
        students: [], teachers: [], balances: {},
        async load() {
            this.students = []; this.teachers = []; this.balances = {};
            if (!this.circleId) return;
            const r = await fetch('{{ route('badges.entitlements.circle-data') }}?circle_id=' + this.circleId, {headers: {'Accept': 'application/json'}});
            if (!r.ok) return;
            const d = await r.json();
            this.students = d.students; this.teachers = d.teachers; this.balances = d.balances || {};
        },
        bal(typeId) { return this.teacherId ? (this.balances[this.teacherId]?.[typeId] ?? 0) : '—'; }
    }" x-init="load()">
        <a href="{{ route('badges.entitlements.index') }}" onclick="if (window.history.length > 1) { window.history.back(); return false; }" class="inline-flex items-center gap-1.5 text-sm font-bold text-gray-500 hover:text-[#0a5c36] mb-4 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            رجوع للخلف
        </a>
        <h1 class="text-xl sm:text-2xl font-black mb-1">استحقاق وتوزيع جديد</h1>
        <p class="text-sm text-gray-500 mb-5 sm:mb-6">طالب واحد وكل الأنواع في نموذج واحد — والتسليم الفوري يوزّع المتاح من رصيد الموزّع والباقي يبقى استحقاقًا بعجز.</p>

        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-2xl p-4 mb-5 text-sm font-bold">
                @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('badges.entitlements.store') }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-6 space-y-5">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">الحلقة</label>
                <select name="circle_id" x-model="circleId" @change="studentId=''; teacherId=''; load()" required class="w-full p-3 border border-gray-200 rounded-xl bg-white text-base sm:text-sm">
                    <option value="">— اختر الحلقة —</option>
                    @foreach ($circles as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">الطالب</label>
                    <select name="student_id" x-model="studentId" required class="w-full p-3 border border-gray-200 rounded-xl bg-white text-base sm:text-sm">
                        <option value="">— اختر الطالب —</option>
                        <template x-for="s in students" :key="s.id"><option :value="s.id" x-text="s.name"></option></template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">المسؤول عن التوزيع</label>
                    <select name="teacher_id" x-model="teacherId" required class="w-full p-3 border border-gray-200 rounded-xl bg-white text-base sm:text-sm">
                        <option value="">— اختر المسؤول —</option>
                        @if (auth()->user()->hasRole(['admin', 'general_manager', 'manager']))
                            <option value="{{ auth()->id() }}">أنا — التوزيع من عهدتي</option>
                        @endif
                        <template x-for="t in teachers" :key="t.id"><option :value="t.id" x-text="t.name"></option></template>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">الكميات حسب النوع (رصيد الموزّع يظهر بجانب كل نوع)</label>
                <div class="border border-gray-200 rounded-xl divide-y divide-gray-100 overflow-hidden">
                    @foreach ($types as $type)
                        <div class="flex items-center gap-3 px-3 sm:px-4 py-3">
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-bold truncate">{{ $type->name }}</div>
                                <div class="text-[11px] text-gray-400">رصيد الموزّع: <span class="font-black text-gray-600" x-text="bal({{ $type->id }})"></span></div>
                                @error('quantities.' . $type->id)<div class="text-[11px] text-red-600 font-bold">{{ $message }}</div>@enderror
                            </div>
                            <input type="number" name="quantities[{{ $type->id }}]" min="0" value="{{ old('quantities.' . $type->id, 1) }}" placeholder="1"
                                class="w-20 sm:w-24 p-2.5 border border-gray-200 rounded-xl text-center text-base sm:text-sm font-bold">
                        </div>
                    @endforeach
                </div>
                @error('quantities')<div class="text-xs text-red-600 font-bold mt-1.5">{{ $message }}</div>@enderror
            </div>

            <label class="flex items-start gap-3 bg-emerald-50 border border-emerald-100 rounded-xl p-3.5 cursor-pointer">
                <input type="checkbox" name="instant" value="1" @checked(old('instant', true)) class="mt-0.5 w-4 h-4 accent-emerald-600">
                <span>
                    <span class="block text-sm font-black text-emerald-900">تسليم فوري للطالب</span>
                    <span class="block text-xs text-emerald-700/80 mt-0.5">يُسلَّم للطالب المتاح من رصيد الموزّع فورًا لكل نوع، وما لا يغطيه الرصيد يبقى استحقاقًا مفتوحًا بعجز يُستكمل لاحقًا من القائمة.</span>
                </span>
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">تاريخ الاستحقاق</label>
                    <input type="date" name="entitlement_date" value="{{ old('entitlement_date', date('Y-m-d')) }}" required class="w-full p-3 border border-gray-200 rounded-xl text-base sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">ملاحظات</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" class="w-full p-3 border border-gray-200 rounded-xl text-base sm:text-sm">
                </div>
            </div>
            <div class="flex flex-col-reverse sm:flex-row gap-3 pt-1">
                <button class="flex-1 py-3 bg-[#0a5c36] text-white font-bold rounded-xl active:scale-[0.99]">حفظ الاستحقاقات</button>
                <a href="{{ route('badges.entitlements.index') }}" class="px-6 py-3 bg-gray-100 text-gray-600 font-bold rounded-xl text-center">إلغاء</a>
            </div>
        </form>
    </div>
</x-layouts.markaz-layout>
