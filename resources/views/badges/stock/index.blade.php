<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6 mb-8">
        <div class="text-right w-full md:w-auto z-10">
            <h1 class="text-3xl font-black mb-2">المخزون المركزي للأوسمة</h1>
            <p class="text-emerald-100/80 text-sm font-medium">رصيد الإدارة = الحركات المسجلة − المسلّم للمعلمين</p>
        </div>
        @if (auth()->user()->hasRole('admin'))
        <a href="{{ route('badges.stock.history') }}" class="px-6 py-3 bg-white/10 hover:bg-white/20 text-white font-bold rounded-2xl">سجل الحركات الكامل</a>
        @endif
        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto mb-8" dir="rtl">
        <table class="w-full text-right">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">نوع الوسام</th>
                    <th class="px-6 py-4 text-sm font-bold text-gray-600">رصيد الإدارة الحالي</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($types as $type)
                <tr>
                    <td class="px-6 py-4 text-sm font-bold">{{ $type->name }}</td>
                    <td class="px-6 py-4 text-sm font-black">{{ $adminBalances[$type->id] ?? 0 }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="2" class="px-6 py-10 text-center text-gray-400 text-sm">لا توجد أنواع أوسمة بعد</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="grid lg:grid-cols-2 gap-6" dir="rtl">
        @if (auth()->user()->hasRole('admin'))
        <form method="POST" action="{{ route('badges.stock.movements.store') }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex flex-col h-full">
            @csrf
            <h2 class="font-black text-lg mb-4">تسجيل حركة مخزون</h2>
            <div class="space-y-4 flex flex-col flex-1">
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">نوع الوسام *</label>
                    <x-searchable-select
                        name="badge_type_id"
                        :options="$types->map(fn($type) => ['value' => $type->id, 'label' => $type->name . ' (الرصيد: ' . ($adminBalances[$type->id] ?? 0) . ')'])->values()"
                        placeholder="اختر نوع الوسام"
                        searchPlaceholder="ابحث عن نوع وسام..."
                        :defaultValue="old('badge_type_id', '')" />
                    <x-input-error :messages="$errors->get('badge_type_id')" class="mt-2" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">الكمية *</label>
                    <input type="number" name="quantity" value="{{ old('quantity') }}" required class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
                    <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">ملاحظات</label>
                    <textarea name="notes" rows="5" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm resize-none">{{ old('notes') }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                </div>
                <button class="mt-auto self-start px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-2xl active:scale-95">تسجيل الحركة</button>
            </div>
        </form>
        @endif

        @if (auth()->user()->hasRole('admin'))
        <form method="POST" action="{{ route('badges.stock.returns.store') }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex flex-col h-full">
            @csrf
            <h2 class="font-black text-lg mb-4">إرجاع أوسمة من معلم إلى الإدارة</h2>
            <div class="space-y-4 flex flex-col flex-1">
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">المعلم *</label>
                    <x-searchable-select
                        name="teacher_id"
                        :options="$teachers->map(fn($teacher) => ['value' => $teacher->id, 'label' => $teacher->name])->values()"
                        placeholder="اختر المعلم"
                        searchPlaceholder="ابحث عن معلم..."
                        :defaultValue="old('teacher_id', '')" />
                    <x-input-error :messages="$errors->get('teacher_id')" class="mt-2" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">نوع الوسام *</label>
                    <x-searchable-select
                        name="badge_type_id"
                        :options="$types->map(fn($type) => ['value' => $type->id, 'label' => $type->name])->values()"
                        placeholder="اختر نوع الوسام"
                        searchPlaceholder="ابحث عن نوع وسام..."
                        :defaultValue="old('badge_type_id', '')" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">الكمية المرجعة *</label>
                    <input type="number" name="quantity" min="1" required class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
                    <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">ملاحظات</label>
                    <input type="text" name="notes" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
                </div>
                <button class="mt-auto self-start px-6 py-3 bg-[#0a5c36] hover:bg-[#0d7a48] text-white font-bold rounded-2xl active:scale-95">تسجيل الإرجاع</button>
            </div>
        </form>
        @endcan
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto mt-8" dir="rtl">
        <div class="px-6 py-4 font-black">أحدث الحركات</div>
        <table class="w-full text-right">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-6 py-3 text-sm font-bold text-gray-600">النوع</th>
                    <th class="px-6 py-3 text-sm font-bold text-gray-600">الحركة</th>
                    <th class="px-6 py-3 text-sm font-bold text-gray-600">الكمية</th>
                    <th class="px-6 py-3 text-sm font-bold text-gray-600">بواسطة</th>
                    <th class="px-6 py-3 text-sm font-bold text-gray-600">التاريخ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($recentMovements as $m)
                <tr>
                    <td class="px-6 py-3 text-sm">{{ $m->badgeType->name }}</td>
                    <td class="px-6 py-3 text-sm">{{ $m->description() }}</td>
                    <td class="px-6 py-3 text-sm font-bold">{{ $m->quantity }}</td>
                    <td class="px-6 py-3 text-sm">{{ $m->creator->name ?? '—' }}</td>
                    <td class="px-6 py-3 text-sm">{{ $m->created_at?->format('Y-m-d H:i') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-gray-400 text-sm">لا حركات بعد</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.markaz-layout>