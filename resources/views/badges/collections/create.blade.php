<x-layouts.markaz-layout>
    @php
        $settledCount = $settled->count();
        $sessionGood = $settled->sum(fn ($s) => collect($s['types'])->sum('good'));
        $sessionLost = $settled->sum(fn ($s) => collect($s['types'])->sum('damaged'));
        $sessionDeducted = $settled->sum(fn ($s) => collect($s['types'])->sum('owed_deducted'));

        // صفوف الطالب المختار: من بيانات التسوية إن كان محفوظًا، وإلا من المتاح
        $rows = [];
        if ($selectedSettled) {
            foreach ($selectedSettled['types'] as $s) {
                $rows[] = [
                    'type' => $s['type'], 'type_id' => $s['type']->id,
                    'entitled' => $s['entitled'], 'delivered' => $s['delivered'],
                    'in_hand' => $s['distributed'],
                    'good' => $s['good'], 'damaged' => $s['damaged'],
                    'owed_total' => $s['owed_available'], 'owed' => $s['owed_deducted'],
                ];
            }
        } elseif ($selectedGroup) {
            foreach ($selectedGroup['types'] as $t) {
                $rows[] = [
                    'type' => $t['type'], 'type_id' => $t['type_id'],
                    'entitled' => $t['entitled'], 'delivered' => $t['delivered'],
                    'in_hand' => $t['collectable'],
                    'good' => $t['collectable'], 'damaged' => 0,
                    'owed_total' => $t['owed'], 'owed' => $t['owed'],
                ];
            }
        }
    @endphp

    {{-- الترويسة بنفس أسلوب صفحات الموقع --}}
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden shadow-xl mb-8">
        <div class="absolute -top-10 -left-10 w-44 h-44 bg-white/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-16 right-24 w-56 h-56 bg-white/10 rounded-full blur-3xl"></div>
        <div class="relative flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black mb-2">تحصيل الأوسمة</h1>
                <p class="text-emerald-100/80 text-sm font-medium">
                    {{ $circle?->name ?? 'لا توجد حلقة متاحة' }}
                    @if ($circle) • {{ $breakdown->count() }} طالب @endif
                    @if ($session) • جلسة مفتوحة: تم حفظ {{ $settledCount }} طالب @endif
                </p>
                <p class="text-emerald-100/60 text-xs mt-1">اختر طالبًا وسجّل نتيجة تحصيله كاملة في خطوة واحدة (السليم + التالف/المفقود + خصم المستحق) ثم احفظه، وفي نهاية الصفحة اعتمد التحصيل كله.</p>
            </div>
            <a href="{{ route('badges.collections.index') }}" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 border border-white/25 text-white px-5 py-2.5 rounded-2xl font-bold text-sm transition">
                رجوع
            </a>
        </div>
    </div>

    {{-- السياق: الحلقة والمسؤول والتاريخ والطالب (يُعاد التحميل عند التغيير) --}}
    <form method="GET" action="{{ route('badges.collections.create') }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6" dir="rtl">
        <div class="grid md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">الحلقة *</label>
                <select name="circle_id" onchange="this.form.submit()" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    @foreach ($circles as $c)
                        <option value="{{ $c->id }}" @selected($circle?->id === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('circle_id')" class="mt-2" />
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">المسؤول عن التحصيل *</label>
                <select name="teacher_id" onchange="this.form.submit()" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    <option value="">— اختر المسؤول —</option>
                    @foreach ($teachers as $t)
                        <option value="{{ $t->id }}" @selected($teacherId === $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('teacher_id')" class="mt-2" />
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">تاريخ التحصيل *</label>
                <input type="date" name="collection_date" value="{{ $collectionDate }}" onchange="this.form.submit()" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5">الطالب *</label>
                <select name="student_id" onchange="this.form.submit()" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                    <option value="">— اختر الطالب —</option>
                    @foreach ($breakdown as $g)
                        <option value="{{ $g['student']->id }}" @selected($selectedStudentId === $g['student']->id)>
                            {{ $g['student']->name }}{{ $settled->has($g['student']->id) ? ' ✓ محفوظ' : '' }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('student_id')" class="mt-2" />
            </div>
        </div>
    </form>

    @if ($errors->has('amounts'))
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-2xl px-5 py-3 text-sm font-bold mb-6" dir="rtl">{{ $errors->first('amounts') }}</div>
    @endif

    @if ($circle && $selectedGroup)
        {{-- بيانات تحصيل الطالب المختار --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-6" dir="rtl">
            <div class="px-6 py-4 flex items-center gap-3 border-b border-gray-50">
                <span class="w-10 h-10 rounded-full flex items-center justify-center text-white font-black shrink-0" style="background-color:#0b3d2c">{{ mb_substr($selectedGroup['student']->name, 0, 1) }}</span>
                <div>
                    <div class="font-black text-gray-800">{{ $selectedGroup['student']->name }}</div>
                    <div class="text-[11px] text-gray-400">
                        @if ($selectedSettled) محفوظ في هذه الجلسة — الحفظ مرة أخرى يستبدل بياناته بالكامل
                        @else لم يُحفظ بعد في هذه الجلسة @endif
                    </div>
                </div>
            </div>

            @if (empty($rows))
                <div class="px-6 py-8 text-center text-sm text-gray-400">لا أوسمة قابلة للتحصيل ولا مستحق لهذا الطالب في هذه الحلقة</div>
            @else
                <form method="POST" action="{{ route('badges.collections.settle-student') }}">
                    @csrf
                    <input type="hidden" name="circle_id" value="{{ $circle->id }}">
                    <input type="hidden" name="teacher_id" value="{{ $teacherId }}">
                    <input type="hidden" name="collection_date" value="{{ $collectionDate }}">
                    <input type="hidden" name="student_id" value="{{ $selectedGroup['student']->id }}">
                    <div class="overflow-x-auto">
                        <table class="w-full text-right">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-bold text-gray-500">نوع الوسام</th>
                                    <th class="px-4 py-3 text-xs font-bold text-gray-500 text-center">الإجمالي</th>
                                    <th class="px-4 py-3 text-xs font-bold text-gray-500 text-center">إجمالي الموزّع</th>
                                    <th class="px-4 py-3 text-xs font-bold text-gray-500 text-center">في يد الطالب</th>
                                    <th class="px-4 py-3 text-xs font-bold text-[#0a5c36] text-center">يُحصَّل من الطالب (سليم)</th>
                                    <th class="px-4 py-3 text-xs font-bold text-orange-600 text-center">تالف/مفقود</th>
                                    <th class="px-4 py-3 text-xs font-bold text-gray-500 text-center">إجمالي المستحق</th>
                                    <th class="px-4 py-3 text-xs font-bold text-amber-700 text-center">يُخصم من المستحق</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($rows as $row)
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-bold">{{ $row['type']->name }}</td>
                                        <td class="px-4 py-3 text-sm text-center font-black">{{ $row['entitled'] }}</td>
                                        <td class="px-4 py-3 text-sm text-center font-black">{{ $row['delivered'] }}</td>
                                        <td class="px-4 py-3 text-sm text-center font-black">{{ $row['in_hand'] }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <input type="number" name="amounts[{{ $row['type_id'] }}][good]" min="0" max="{{ $row['in_hand'] }}" value="{{ $row['good'] }}" class="w-20 p-2 border border-gray-200 rounded-xl text-sm text-center">
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <input type="number" name="amounts[{{ $row['type_id'] }}][damaged]" min="0" max="{{ $row['in_hand'] }}" value="{{ $row['damaged'] }}" class="w-20 p-2 border border-gray-200 rounded-xl text-sm text-center">
                                        </td>
                                        <td class="px-4 py-3 text-sm text-center font-black" style="color:#b45309">{{ $row['owed_total'] }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <input type="number" name="amounts[{{ $row['type_id'] }}][owed]" min="0" max="{{ $row['owed_total'] }}" value="{{ $row['owed'] }}" class="w-20 p-2 border border-gray-200 rounded-xl text-sm text-center">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-50 flex items-center justify-between gap-3 flex-wrap">
                        <p class="text-[11px] text-gray-400">ما لم يُحصَّل سليمًا ولم يُسجَّل تالفًا/مفقودًا يبقى في يد الطالب قابلًا للتحصيل لاحقًا. التالف/المفقود يُسوّى من حساب الطالب ولا يعود لعهدة المسؤول.</p>
                        <button class="px-6 py-2.5 bg-[#0a5c36] hover:bg-[#0b3d2c] text-white rounded-xl font-bold text-sm" @disabled(!$teacherId)>
                            {{ $selectedSettled ? 'حفظ تعديلات تحصيل الطالب' : 'حفظ تحصيل الطالب' }}
                        </button>
                    </div>
                    @if (!$teacherId)
                        <p class="px-6 pb-4 text-xs font-bold text-red-600">اختر المسؤول عن التحصيل أولًا لتتمكن من الحفظ</p>
                    @endif
                </form>
            @endif
        </div>
    @elseif ($circle)
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-6 py-10 text-center text-gray-400 text-sm mb-6" dir="rtl">اختر طالبًا من قائمة الطلاب بالأعلى لبدء تحصيله</div>
    @endif

    {{-- الطلاب المحفوظون في هذه الجلسة --}}
    @if ($session && $settledCount > 0)
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-6" dir="rtl">
            <div class="px-6 py-4 border-b border-gray-100 font-black text-gray-800">تم حفظهم في هذه الجلسة ({{ $settledCount }})</div>
            <div class="divide-y divide-gray-50">
                @foreach ($breakdown as $g)
                    @if ($settled->has($g['student']->id))
                        <div class="px-6 py-3 flex items-center justify-between gap-3">
                            <span class="text-sm font-bold">{{ $g['student']->name }}</span>
                            <span class="text-xs text-gray-400">
                                سليم {{ collect($settled[$g['student']->id]['types'])->sum('good') }} •
                                تالف/مفقود {{ collect($settled[$g['student']->id]['types'])->sum('damaged') }} •
                                مخصوم من المستحق {{ collect($settled[$g['student']->id]['types'])->sum('owed_deducted') }}
                            </span>
                            <a class="text-xs font-bold text-[#0a5c36]" href="{{ route('badges.collections.create', ['circle_id' => $circle->id, 'teacher_id' => $teacherId, 'collection_date' => $collectionDate, 'student_id' => $g['student']->id]) }}">فتح</a>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    {{-- اعتماد التحصيل في آخر الصفحة --}}
    @if ($circle)
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6" dir="rtl">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="font-black text-gray-800 mb-1">اعتماد التحصيل</div>
                    <p class="text-xs text-gray-400">
                        @if ($session)
                            الجلسة الحالية: {{ $settledCount }} طالب محفوظ • سليم {{ $sessionGood }} • تالف/مفقود {{ $sessionLost }} • مخصوم من المستحق {{ $sessionDeducted }}.
                            الاعتماد يُدخل السليم في عهدة المسؤول ويُقفل الجلسة نهائيًا.
                        @else
                            لا جلسة مفتوحة بعد — تُفتح الجلسة تلقائيًا مع حفظ أول طالب.
                        @endif
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    @if ($session)
                        <form method="POST" action="{{ route('badges.collections.cancel', $session) }}" data-swal-confirm data-swal-icon="warning" data-swal-title="إلغاء الجلسة؟" data-swal-text="سيتم حذف بنود الطلاب المحفوظين وعكس خصومات المستحق، وتعود الأوسمة كما كانت." data-swal-confirm-text="نعم، إلغاء الجلسة" data-swal-color="#dc2626">
                            @csrf
                            <button class="px-5 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-xl font-bold text-sm">إلغاء الجلسة</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ $session ? route('badges.collections.complete', $session) : '#' }}" data-swal-confirm data-swal-icon="question" data-swal-title="اعتماد التحصيل؟" data-swal-text="بعد الاعتماد تدخل الكميات المحصلة السليمة في عهدة المسؤول وتُقفل الجلسة نهائيًا ولا يمكن التعديل." data-swal-confirm-text="نعم، اعتماد التحصيل" data-swal-color="#059669">
                        @csrf
                        <button class="px-6 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-white rounded-xl font-bold text-sm shadow" @disabled(!$session || $settledCount === 0)>اعتماد التحصيل</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- تأكيدات SweetAlert بنفس أسلوب حورات الموقع بدل تأكيد المتصفح --}}
    @push('scripts')
        <script>
            document.querySelectorAll('form[data-swal-confirm]').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    Swal.fire({
                        title: form.dataset.swalTitle,
                        text: form.dataset.swalText,
                        icon: form.dataset.swalIcon || 'warning',
                        showCancelButton: true,
                        confirmButtonText: form.dataset.swalConfirmText,
                        cancelButtonText: 'تراجع',
                        confirmButtonColor: form.dataset.swalColor || '#059669',
                        cancelButtonColor: '#6b7280',
                        reverseButtons: true,
                        customClass: {
                            popup: 'rounded-3xl font-bold',
                            confirmButton: 'rounded-xl px-6 py-2.5 text-sm',
                            cancelButton: 'rounded-xl px-6 py-2.5 text-sm',
                        },
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });
        </script>
    @endpush
</x-layouts.markaz-layout>
