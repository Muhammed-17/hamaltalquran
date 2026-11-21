<x-layouts.markaz-layout>
    <div class="w-full max-w-3xl mx-auto" dir="rtl">
        <div class="bg-[#0b3d2c] rounded-3xl p-5 sm:p-6 lg:p-8 text-white shadow-xl mb-6 sm:mb-8">
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-black mb-1.5">تسجيل استحقاق وسام لطالب</h1>
            <p class="text-emerald-100/80 text-xs sm:text-sm">يُحفظ الاستحقاق كاملًا حتى لو لم يتوفر رصيد لدى المعلم، ويُفتح عجز تلقائيًا عند النقص</p>
        </div>

        <form method="POST" action="{{ route('badges.entitlements.store') }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-6 lg:p-8 w-full"
            x-data="{ circleId: '{{ old('circle_id') }}', students: [], teachers: [],
                async load() {
                    this.students = []; this.teachers = [];
                    if (!this.circleId) return;
                    const res = await fetch(`{{ route('badges.entitlements.circle-data') }}?circle_id=${this.circleId}`, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) return;
                    const data = await res.json();
                    this.students = data.students; this.teachers = data.teachers;
                }
            }" x-init="load()">
            @csrf
            <div class="space-y-4 sm:space-y-5">
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">الحلقة *</label>
                    <select name="circle_id" x-model="circleId" @change="load()" required class="w-full p-3 sm:p-2.5 px-4 border border-gray-200 rounded-xl text-base sm:text-sm bg-white">
                        <option value="">— اختر الحلقة —</option>
                        @foreach ($circles as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    <x-input-error :messages="$errors->get('circle_id')" class="mt-2" />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 mb-1.5">الطالب *</label>
                        <select name="student_id" required class="w-full p-3 sm:p-2.5 px-4 border border-gray-200 rounded-xl text-base sm:text-sm bg-white">
                            <option value="">— اختر الحلقة أولًا —</option>
                            <template x-for="s in students" :key="s.id"><option :value="s.id" x-text="s.name"></option></template>
                        </select>
                        <x-input-error :messages="$errors->get('student_id')" class="mt-2" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 mb-1.5">المعلم المسؤول *</label>
                        <select name="teacher_id" required class="w-full p-3 sm:p-2.5 px-4 border border-gray-200 rounded-xl text-base sm:text-sm bg-white">
                            <option value="">— اختر الحلقة أولًا —</option>
                            <template x-for="t in teachers" :key="t.id"><option :value="t.id" x-text="t.name"></option></template>
                        </select>
                        <x-input-error :messages="$errors->get('teacher_id')" class="mt-2" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">نوع الوسام *</label>
                    <select name="badge_type_id" required class="w-full p-3 sm:p-2.5 px-4 border border-gray-200 rounded-xl text-base sm:text-sm bg-white">
                        <option value="">— اختر النوع —</option>
                        @foreach ($types as $type)<option value="{{ $type->id }}" @selected(old('badge_type_id') == $type->id)>{{ $type->name }}</option>@endforeach
                    </select>
                    <x-input-error :messages="$errors->get('badge_type_id')" class="mt-2" />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 mb-1.5">الكمية المستحقة *</label>
                        <input type="number" name="quantity" min="1" value="{{ old('quantity') }}" required class="w-full p-3 sm:p-2.5 px-4 border border-gray-200 rounded-xl text-base sm:text-sm">
                        <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 mb-1.5">تاريخ الاستحقاق *</label>
                        <input type="date" name="entitlement_date" value="{{ old('entitlement_date', date('Y-m-d')) }}" required class="w-full p-3 sm:p-2.5 px-4 border border-gray-200 rounded-xl text-base sm:text-sm">
                        <x-input-error :messages="$errors->get('entitlement_date')" class="mt-2" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">ملاحظات</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" class="w-full p-3 sm:p-2.5 px-4 border border-gray-200 rounded-xl text-base sm:text-sm">
                </div>
                <div class="flex flex-col sm:flex-row gap-3 pt-1">
                    <button class="w-full sm:w-auto px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-2xl active:scale-95">تسجيل الاستحقاق</button>
                    <a href="{{ route('badges.entitlements.index') }}" class="w-full sm:w-auto px-6 py-3 bg-gray-100 font-bold rounded-2xl text-center">إلغاء</a>
                </div>
            </div>
        </form>
    </div>
</x-layouts.markaz-layout>
