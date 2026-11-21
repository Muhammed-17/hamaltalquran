<x-layouts.markaz-layout>
    <div class="w-full max-w-3xl mx-auto" dir="rtl">
        <div class="bg-[#0b3d2c] rounded-3xl p-5 sm:p-6 lg:p-8 text-white shadow-xl mb-6 sm:mb-8">
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-black mb-1.5">تسليم أوسمة لمعلم</h1>
            <p class="text-emerald-100/80 text-xs sm:text-sm">أدخل كمية كل نوع مطلوب في نفس النموذج — تُخصم الكميات من {{ $balanceLabel }} وتُضاف لعهدة المستلم فورًا</p>
        </div>

        <form method="POST" action="{{ route('badges.handovers.store') }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-6 lg:p-8 w-full">
            @csrf
            <div class="space-y-4 sm:space-y-5">
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">المستلم *</label>
                    <select name="teacher_id" required class="w-full p-3 sm:p-2.5 px-4 border border-gray-200 rounded-xl text-base sm:text-sm bg-white">
                        <option value="">— اختر المستلم —</option>
                        @foreach ($teachers as $t)<option value="{{ $t->id }}" @selected(old('teacher_id') == $t->id)>{{ $t->name }} ({{ $access->custodyRoleLabel($t) }})</option>@endforeach
                    </select>
                    <x-input-error :messages="$errors->get('teacher_id')" class="mt-2" />
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">الأوسمة والكميات *</label>
                    <div class="border border-gray-200 rounded-xl divide-y divide-gray-100 overflow-hidden">
                        @forelse ($types as $type)
                            <div class="flex items-center justify-between gap-3 p-3 sm:px-4">
                                <div class="min-w-0">
                                    <div class="text-sm font-bold truncate">{{ $type->name }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $balanceLabel }}: {{ $adminBalances[$type->id] ?? 0 }}</div>
                                </div>
                                <div class="shrink-0 w-28 sm:w-32">
                                    <input type="number" name="quantities[{{ $type->id }}]" min="0" inputmode="numeric"
                                        value="{{ old('quantities.' . $type->id) }}" placeholder="0"
                                        class="w-full p-2.5 px-3 border border-gray-200 rounded-xl text-base sm:text-sm text-center">
                                </div>
                            </div>
                            @if ($errors->has('quantities.' . $type->id))
                                <div class="px-3 sm:px-4 pb-3 -mt-1 text-xs text-red-600">{{ $errors->first('quantities.' . $type->id) }}</div>
                            @endif
                        @empty
                            <div class="p-4 text-sm text-gray-400 text-center">لا توجد أنواع أوسمة مفعّلة بعد</div>
                        @endforelse
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1.5">اترك الكمية صفرًا أو فارغة للأنواع غير المطلوبة — يُسجَّل تسليم مستقل لكل نوع له كمية، ولو نوع تجاوز الرصيد المتاح لن يُسجَّل أي تسليم.</p>
                    <x-input-error :messages="$errors->get('quantities')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 mb-1.5">تاريخ التسليم *</label>
                        <input type="date" name="handover_date" value="{{ old('handover_date', date('Y-m-d')) }}" required class="w-full p-3 sm:p-2.5 px-4 border border-gray-200 rounded-xl text-base sm:text-sm">
                        <x-input-error :messages="$errors->get('handover_date')" class="mt-2" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 mb-1.5">ملاحظات</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" class="w-full p-3 sm:p-2.5 px-4 border border-gray-200 rounded-xl text-base sm:text-sm">
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-1">
                    <button class="w-full sm:w-auto px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-2xl active:scale-95">تنفيذ التسليم</button>
                    <a href="{{ route('badges.handovers.index') }}" class="w-full sm:w-auto px-6 py-3 bg-gray-100 font-bold rounded-2xl text-center">إلغاء</a>
                </div>
            </div>
        </form>
    </div>
</x-layouts.markaz-layout>
