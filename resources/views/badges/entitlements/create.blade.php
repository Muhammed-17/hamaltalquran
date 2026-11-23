<x-layouts.markaz-layout>
    <div dir="rtl">
        {{-- ترويسة بنفس نمط ترويسات صفحات الأوسمة في الموقع --}}
        <div class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between md:items-center shadow-xl gap-6 mb-8">
            <div class="text-right w-full md:w-auto z-10">
                <h1 class="text-3xl font-black mb-2">التسليم اليومي</h1>
                <p class="text-emerald-100/80 text-sm font-medium">
                    @if ($circle && $distributor)
                        {{ $date }} • {{ $circle->name }} • الموزّع: {{ $distributor->name }}
                    @else
                        ورقة عمل اليوم: وسام واحد من كل نوع لكل طالب
                    @endif
                </p>
                @if ($circle && $distributor && $students->isNotEmpty())
                    @php
                        $completedToday = 0; $grandRemaining = 0;
                        foreach ($students as $s) {
                            $due = 0; $done = 0;
                            foreach ($types as $t) {
                                $c = $sheet[$s->id][$t->id] ?? [];
                                $grandRemaining += (int) ($c['remaining'] ?? 0);
                                $q = (int) ($c['today'] ?? 0);
                                if ($q > 0) { $due++; if ((int) ($c['delivered_today'] ?? 0) >= $q) { $done++; } }
                            }
                            if ($due > 0 && $done === $due) { $completedToday++; }
                        }
                    @endphp
                    <p class="text-emerald-100/70 text-xs font-bold mt-2">{{ $students->count() }} طالب • اكتمل تسليم {{ $completedToday }} اليوم • إجمالي متبقّي الحلقة {{ $grandRemaining }}</p>
                @endif
            </div>
            @if ($circle && $distributor && $types->isNotEmpty())
                <div class="flex flex-col gap-2 z-10 shrink-0">
                    <a href="{{ route('badges.entitlements.index') }}" class="self-start inline-flex items-center gap-1.5 px-5 py-2.5 bg-white/10 hover:bg-white/20 border border-white/25 text-white text-sm font-bold rounded-2xl transition active:scale-95">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        رجوع
                    </a>
                    <span class="text-[11px] font-bold text-emerald-100/70">رصيد الموزّع الآن</span>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($types as $type)
                            <span class="text-xs font-black bg-white/10 border border-white/25 rounded-full px-3 py-1.5">{{ $type->name }}: {{ $distributorBalances[$type->id] ?? 0 }}</span>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
            <div class="absolute -left-6 -top-10 w-32 h-32 bg-white/10 rounded-full blur-3xl"></div>
        </div>

        {{-- سياق الورقة: التاريخ والحلقة والموزّع (إعادة تحميل عند التغيير) --}}
        <form method="GET" action="{{ route('badges.entitlements.create') }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6">
            <div class="flex flex-col lg:flex-row lg:flex-wrap gap-3 items-end">
                <div class="w-full lg:flex-1 lg:min-w-[150px]">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">التاريخ</label>
                    <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                </div>
                <div class="w-full lg:flex-1 lg:min-w-[150px]">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">الحلقة</label>
                    <select name="circle_id" onchange="this.form.submit()" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                        @foreach ($circles as $c)
                            <option value="{{ $c->id }}" @selected($circle && $c->id === $circle->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-full lg:flex-1 lg:min-w-[150px]">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">الموزّع</label>
                    <select name="teacher_id" onchange="this.form.submit()" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
                        @foreach ($distributors as $d)
                            <option value="{{ $d->id }}" @selected($distributor && $d->id === $distributor->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="text-[11px] text-gray-400 mt-3">اضغط على اسم الوسام نفسه لتعليم استحقاق اليوم (يتغيّر لونه وتتلوّن بطاقة الطالب)، واضغط «تسليم فوري» في الطرف المقابل إن سلّمته فعلًا — ثم احفظ من نهاية الصفحة.</p>
        </form>

        {{-- تنبيهات: كميات تعذّر حذفها/تقليلها أو إلغاء تسليمها --}}
        @if (session('sheet_floored'))
            <div class="rounded-2xl border p-4 mb-6 text-sm" style="background-color:#fffbeb;border-color:#fde68a;color:#92400e">
                <div class="font-black mb-1">تعذّر حذف/تقليل بعض الكميات لأنها سُلّمت أو دخلت جولة تحصيل معتمدة:</div>
                <ul class="list-disc pr-5 space-y-0.5">
                    @foreach (session('sheet_floored') as $f)
                        <li>{{ $f['student'] }} — {{ $f['type'] }}: بمقدار {{ $f['quantity'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (!$circle)
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-10 text-center text-gray-400 text-sm">لا توجد حلقات داخل نطاق وصولك.</div>
        @elseif (!$distributor)
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-10 text-center text-gray-400 text-sm">لا يوجد موزّع مسؤول عن هذه الحلقة.</div>
        @elseif ($students->isEmpty())
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-10 text-center text-gray-400 text-sm">لا يوجد طلاب مقيّدون في هذه الحلقة.</div>
        @else
            <form method="POST" action="{{ route('badges.entitlements.daily-store') }}" id="sheet-form">
                @csrf
                <input type="hidden" name="date" value="{{ $date }}">
                <input type="hidden" name="circle_id" value="{{ $circle->id }}">
                <input type="hidden" name="teacher_id" value="{{ $distributor->id }}">

                {{-- أزرار مساعدة للتعليم الجماعي --}}
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <button type="button" id="activate-all-entitlements" class="px-4 py-2.5 bg-white border-2 border-emerald-500 text-emerald-700 hover:bg-emerald-50 text-sm font-black rounded-xl transition active:scale-95">تفعيل كل الأوسمة</button>
                    <button type="button" id="activate-all-deliveries" class="px-4 py-2.5 bg-white border-2 text-sm font-black rounded-xl transition active:scale-95" style="border-color:#0b3d2c;color:#0b3d2c">تفعيل التسليم الفوري للجميع</button>
                    <span class="text-[11px] text-gray-400">أزرار مساعدة للتعليم فقط — لا يُحفظ شيء إلا بزر «حفظ التسليم» في نهاية الصفحة.</span>
                </div>

                <div class="space-y-3">
                    @foreach ($students as $student)
                        @php
                            $totalRemaining = 0; $dueToday = 0; $doneToday = 0;
                            foreach ($types as $t) {
                                $c = $sheet[$student->id][$t->id] ?? [];
                                $totalRemaining += (int) ($c['remaining'] ?? 0);
                                $q = (int) ($c['today'] ?? 0);
                                if ($q > 0) { $dueToday++; if ((int) ($c['delivered_today'] ?? 0) >= $q) { $doneToday++; } }
                            }
                            $statusLabel = $dueToday === 0 ? 'لم يبدأ اليوم' : ($doneToday === $dueToday ? 'اكتمل اليوم' : 'جارٍ اليوم');
                            $statusStyle = $dueToday === 0
                                ? 'background-color:#f3f4f6;color:#6b7280'
                                : ($doneToday === $dueToday ? 'background-color:#d1fae5;color:#065f46' : 'background-color:#fef3c7;color:#92400e');
                            $cardStyle = 'background-color:#ffffff;border-color:#f3f4f6';
                            if ($dueToday > 0 && $doneToday === $dueToday) {
                                $cardStyle = 'background-color:#ecfdf5;border-color:#6ee7b7';
                            } elseif ($dueToday > 0) {
                                $cardStyle = 'background-color:#fffbeb;border-color:#fcd34d';
                            }
                        @endphp
                        <div class="rounded-3xl p-5 md:p-6 shadow-sm border flex flex-col gap-4 transition-colors" style="{{ $cardStyle }}" data-student-card="{{ $student->id }}">
                            {{-- رأس البطاقة: الطالب وحالته اليوم ومستحقّه غير المسلّم --}}
                            <div class="flex items-center gap-4 flex-wrap">
                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 text-white font-black text-lg" style="background-color:#0b3d2c">{{ mb_substr($student->name, 0, 1) }}</div>
                                <div class="text-right flex-1 min-w-[120px]">
                                    <h4 class="text-lg font-black text-gray-800">{{ $student->name }}</h4>
                                    <p class="text-gray-400 text-[11px]">مستحق ولم يستلم بعد عند هذا الموزّع: {{ $totalRemaining }}</p>
                                </div>
                                <span class="text-[11px] font-black rounded-full px-3 py-1.5" style="{{ $statusStyle }}">{{ $statusLabel }}</span>
                            </div>

                            {{-- الأوسمة: الوسام زر لوني، والتسليم الفوري في الطرف المقابل من صفه --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach ($types as $type)
                                    @php
                                        $cell = $sheet[$student->id][$type->id] ?? [];
                                        $todayQty = (int) ($cell['today'] ?? 0);
                                        $entitled = $todayQty >= 1;
                                        $delivered = $todayQty > 0 && (int) ($cell['delivered_today'] ?? 0) >= $todayQty;
                                    @endphp
                                    <div class="border border-gray-200 rounded-2xl p-3 flex flex-col gap-2 bg-white/70">
                                        <div class="flex items-center gap-2">
                                            <label class="cursor-pointer select-none flex-1">
                                                <input type="checkbox" name="rows[{{ $student->id }}][{{ $type->id }}]" value="1"
                                                    data-initial="{{ $entitled ? 1 : 0 }}" data-type-id="{{ $type->id }}" @checked($entitled) class="sr-only">
                                                <span class="flex items-center justify-center gap-2 w-full px-3 py-2.5 rounded-xl border-2 font-black text-sm transition-all type-toggle" data-role="ent"
                                                    style="{{ $entitled ? 'background-color:#059669;border-color:#059669;color:#fff' : 'background-color:#fff;border-color:#e5e7eb;color:#374151' }}">
                                                    <svg class="chip-check w-4 h-4 shrink-0" style="{{ $entitled ? '' : 'display:none' }}" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                    {{ $type->name }}
                                                </span>
                                            </label>
                                            <label class="cursor-pointer select-none shrink-0">
                                                <input type="checkbox" name="deliver[{{ $student->id }}][{{ $type->id }}]" value="1"
                                                    data-initial="{{ $delivered ? 1 : 0 }}" data-type-id="{{ $type->id }}" @checked($delivered) class="sr-only">
                                                <span class="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl border-2 font-black text-xs transition-all type-toggle" data-role="deliver"
                                                    style="{{ $delivered ? 'background-color:#0b3d2c;border-color:#0b3d2c;color:#fff' : 'background-color:#fff;border-color:#e5e7eb;color:#6b7280' }}">
                                                    <svg class="chip-check w-3.5 h-3.5 shrink-0" style="{{ $delivered ? '' : 'display:none' }}" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                    تسليم فوري
                                                </span>
                                            </label>
                                        </div>
                                        @if (($cell['total'] ?? 0) > 0)
                                            <div class="text-[11px] text-gray-400">
                                                متبقّي {{ $cell['remaining'] ?? 0 }} • سلّم اليوم {{ $cell['delivered_today'] ?? 0 }}
                                                @if ($todayQty > 1)
                                                    • سُجّل اليوم: {{ $todayQty }}
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <p class="text-[11px] text-gray-400 border-t border-gray-100 pt-3">«تسليم فوري» وحدها (دون تعليم الوسام) تُسلّم متبقّيات الطالب الأقدم من هذا النوع. وإزالة تعليم «تسليم فوري» عند الحفظ تُلغي تسليم اليوم ويرجع الوسام للموزّع، وإزالة تعليم الوسام نفسه تحذف استحقاق اليوم فعليًا.</p>
                        </div>
                    @endforeach
                </div>

                {{-- حفظ التسليم في نهاية الصفحة (غير مثبت) --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mt-6 flex flex-col sm:flex-row items-center gap-3">
                    <button type="submit" class="w-full sm:w-auto px-10 py-3.5 bg-emerald-500 hover:bg-emerald-400 text-white font-black rounded-2xl shadow-lg transition-all active:scale-95">حفظ التسليم</button>
                    <span id="no-changes-note" class="hidden text-sm font-bold" style="color:#b45309">لا توجد تعديلات — علّم وسامًا أولًا بالضغط عليه.</span>
                    <span class="text-[11px] text-gray-400">يُحفظ المعدّل فقط: الاستحقاقات والتسليمات وإلغاءاتها.</span>
                </div>
            </form>

            <script>
                (function () {
                    var form = document.getElementById('sheet-form');
                    if (!form) return;
                    var note = document.getElementById('no-changes-note');

                    function paintToggle(input) {
                        var label = input.closest('label');
                        var span = label ? label.querySelector('.type-toggle') : null;
                        if (!span) return;
                        var color = span.dataset.role === 'deliver' ? '#0b3d2c' : '#059669';
                        var check = span.querySelector('.chip-check');
                        if (input.checked) {
                            span.style.backgroundColor = color; span.style.borderColor = color; span.style.color = '#fff';
                            if (check) check.style.display = '';
                        } else {
                            span.style.backgroundColor = '#fff'; span.style.borderColor = '#e5e7eb';
                            span.style.color = span.dataset.role === 'deliver' ? '#6b7280' : '#374151';
                            if (check) check.style.display = 'none';
                        }
                    }

                    // تلوين البطاقة كلها حسب حالة الطالب: مكتمل أخضر، جارٍ أصفر، لم يبدأ أبيض
                    function paintCard(card) {
                        var due = 0, done = 0;
                        card.querySelectorAll('input[name^="rows["]').forEach(function (ent) {
                            var del = card.querySelector('input[name^="deliver["][data-type-id="' + ent.dataset.typeId + '"]');
                            var delivered = del && del.checked;
                            if (ent.checked || delivered) due++;
                            if (delivered) done++;
                        });
                        if (due > 0 && done === due) {
                            card.style.backgroundColor = '#ecfdf5'; card.style.borderColor = '#6ee7b7';
                        } else if (due > 0) {
                            card.style.backgroundColor = '#fffbeb'; card.style.borderColor = '#fcd34d';
                        } else {
                            card.style.backgroundColor = '#ffffff'; card.style.borderColor = '#f3f4f6';
                        }
                    }

                    function cardDirty(card) {
                        var dirty = false;
                        card.querySelectorAll('input[type="checkbox"][data-initial]').forEach(function (inp) {
                            if ((inp.checked ? '1' : '0') !== inp.dataset.initial) dirty = true;
                        });
                        return dirty;
                    }
                    form.querySelectorAll('input[type="checkbox"][data-initial]').forEach(function (inp) {
                        inp.addEventListener('change', function () {
                            paintToggle(inp);
                            var card = inp.closest('[data-student-card]');
                            if (card) paintCard(card);
                            if (note) note.classList.add('hidden');
                        });
                    });

                    // الأزرار المساعدة: تعليم جماعي لنوع واحد من الخانات في الصفحة كلها
                    function setAll(namePrefix) {
                        form.querySelectorAll('input[type="checkbox"][name^="' + namePrefix + '"]').forEach(function (inp) {
                            if (!inp.checked) {
                                inp.checked = true;
                                paintToggle(inp);
                            }
                        });
                        form.querySelectorAll('[data-student-card]').forEach(paintCard);
                        if (note) note.classList.add('hidden');
                    }
                    var allEnt = document.getElementById('activate-all-entitlements');
                    if (allEnt) allEnt.addEventListener('click', function () { setAll('rows['); });
                    var allDel = document.getElementById('activate-all-deliveries');
                    if (allDel) allDel.addEventListener('click', function () { setAll('deliver['); });

                    // الحفظ: البطاقات المعدّلة فقط، وتتحول اختياراتها (استحقاق/تسليم) لقيم صريحة 1/0
                    form.addEventListener('submit', function (e) {
                        var dirtyCards = 0;
                        form.querySelectorAll('[data-student-card]').forEach(function (card) {
                            if (cardDirty(card)) {
                                dirtyCards++;
                                card.querySelectorAll('input[type="checkbox"][data-initial]').forEach(function (inp) {
                                    var hidden = document.createElement('input');
                                    hidden.type = 'hidden';
                                    hidden.name = inp.name;
                                    hidden.value = inp.checked ? '1' : '0';
                                    form.appendChild(hidden);
                                    inp.disabled = true;
                                });
                            } else {
                                card.querySelectorAll('input[type="checkbox"][data-initial]').forEach(function (inp) { inp.disabled = true; });
                            }
                        });
                        if (dirtyCards === 0) {
                            e.preventDefault();
                            if (note) note.classList.remove('hidden');
                        }
                    });
                })();
            </script>
        @endif
    </div>
</x-layouts.markaz-layout>
