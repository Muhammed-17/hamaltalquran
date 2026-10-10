<x-layouts.markaz-layout>
    <div class="space-y-6" x-data="{
        students: {{ Js::from(
            $students->map(function ($s) use ($attendanceData) {
                $status = $attendanceData[$s->id]->status ?? 'not_recorded';
                $notes  = $attendanceData[$s->id]->notes ?? '';
                $card   = $attendanceData[$s->id]->card ?? 0;
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'status' => $status,
                    'notes' => $notes,
                    'card' => $card,
                    'original_status' => $status,
                    'original_notes' => $notes,
                    'original_card' => $card,
                ];
            }),
        ) }},
        filter: 'all',

        submitForm() {
    // ✅ الطلاب اللي عندهم حالة مسجّلة أصلاً
    const touched = this.students.filter(s => s.status !== 'not_recorded');

    if (touched.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'تنبيه',
            text: 'يرجى تسجيل حضور طالب واحد على الأقل',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#0a5c36'
        });
        return;
    }

    const selectedDate = document.querySelector('input[name=date]')?.value;
    const today = new Date().toISOString().split('T')[0];
    if (selectedDate > today) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: 'لا يمكن تسجيل الحضور لتاريخ مستقبلي',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#0a5c36'
        });
        return;
    }

    // ✅ إرسال الطلاب الجدد (لم يكن لهم سجل سابق) أو الذين تغيّرت حالتهم/ملاحظاتهم فقط
    const changed = touched.filter(s =>
    s.original_status === 'not_recorded' || s.status !== s.original_status || (s.notes ?? '') !== (s.original_notes ?? '') ||
    Number(s.card ?? 0) !== Number(s.original_card ?? 0)
    );

    if (changed.length === 0) {
        Swal.fire({
            icon: 'info',
            title: 'تنبيه',
            text: 'لا يوجد أي تعديل جديد لحفظه',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#0a5c36'
        });
        return;
    }

    const form = document.getElementById('attendance-form');
    form.querySelectorAll('input[name^=attendance]').forEach(el => el.remove());

    changed.forEach((s, i) => {
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = `attendance[${i}][student_id]`;
        idInput.value = s.id;
        form.appendChild(idInput);

        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = `attendance[${i}][status]`;
        statusInput.value = s.status;
        form.appendChild(statusInput);

        const notesInput = document.createElement('input');
        notesInput.type = 'hidden';
        notesInput.name = `attendance[${i}][notes]`;
        notesInput.value = s.notes ?? '';
        form.appendChild(notesInput);
    });

    form.submit();
    const cardInput = document.createElement('input');
    cardInput.type = 'hidden';
    cardInput.name = `attendance[${i}][card]`;
    cardInput.value = Number(s.card ?? 0);
    form.appendChild(cardInput);
},

        get totalCount() { return this.students.length },
        get recordedCount() { return this.students.filter(s => s.status !== 'not_recorded').length },
        get progressPercentage() { return Math.round((this.recordedCount / this.totalCount) * 100) || 0 },
        get isCompleted() { return this.recordedCount === this.totalCount && this.totalCount > 0 },

        get filteredStudents() {
            if (this.filter === 'all') return this.students;
            return this.students.filter(s => s.status === this.filter);
        },

        allPresent() {
            this.students.forEach(s => {
                if (s.status === 'not_recorded') s.status = 'present';
            });
        }
    }">
        <!-- Header Card -->
        <div class="bg-[#0a4d31] rounded-[2.5rem] p-8 text-white relative flex flex-col md:flex-row justify-between items-center shadow-2xl gap-8">
            <div class="text-right w-full md:w-auto z-10">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 bg-emerald-400/20 rounded-xl flex items-center justify-center border border-emerald-400/30">
                        <svg class="w-6 h-6 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                    </div>
                    <h1 class="text-3xl font-black">تسجيل الحضور</h1>
                </div>
                <p class="text-emerald-100/60 text-sm font-medium pr-1">متابعة دقيقة لحلقات مركز حملة القرآن</p>

                <div class="flex gap-2 mt-6">
                    <a href="{{ route('attendance.index') }}"
                        class="z-10 inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 border border-white/20 text-white px-6 py-3 rounded-2xl font-bold transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        الرجوع للحضور
                    </a>
                </div>
            </div>

            {{-- ✅ الفورم مع فلاتر الحلقة والمعلم --}}
            <form action="{{ route('attendance.create') }}" method="GET"
                class="flex flex-col md:flex-row gap-4 w-full md:w-auto shrink-0 items-end"
                id="attendanceFilterForm">

                {{-- فلتر التاريخ --}}
                <div>
                    <label class="block text-xs font-bold text-emerald-100/80 mb-1.5">التاريخ</label>
                    <input type="date" name="date" value="{{ $date }}"
                        max="{{ now()->format('Y-m-d') }}"
                        onchange="document.getElementById('attendanceFilterForm').submit()"
                        class="bg-white/10 hover:bg-white/20 border border-white/10 rounded-2xl px-5 py-3.5 text-white focus:outline-none focus:ring-4 focus:ring-emerald-400/20 transition-all font-bold appearance-none h-11">
                </div>

                {{-- ✅ فلتر المعلم بـ searchable-select --}}
                @if($teachers->isNotEmpty())
                <div class="w-full md:w-56">
                    <label class="block text-xs font-bold text-emerald-100/80 mb-1.5">المعلم</label>
                    <x-searchable-select
                        name="teacher_id"
                        placeholder="كل المعلمين"
                        search-placeholder="ابحث عن معلم..."
                        :default-value="$selectedTeacherId ?? ''"
                        :options="$teachers->map(fn($t) => ['value' => $t->id, 'label' => $t->name])"
                        @searchable-change.window="
                    if ($event.detail.name === 'teacher_id') {
                        const url = new URL(window.location.href);
                        if ($event.detail.value) {
                            url.searchParams.set('teacher_id', $event.detail.value);
                        } else {
                            url.searchParams.delete('teacher_id');
                        }
                        url.searchParams.set('date', '{{ $date }}');
                        window.location.href = url.toString();
                    }
                " />
                </div>
                @endif

                {{-- ✅ فلتر الحلقة بـ searchable-select --}}
                <div class="w-full md:w-56">
                    <label class="block text-xs font-bold text-emerald-100/80 mb-1.5">الحلقة</label>
                    <x-searchable-select
                        name="circle_id"
                        placeholder="اختر الحلقة..."
                        search-placeholder="ابحث عن حلقة..."
                        :default-value="$selectedCircleId"
                        :options="$circles->map(fn($c) => ['value' => $c->id, 'label' => $c->name])"
                        @searchable-change.window="
        if ($event.detail.name === 'circle_id') {
            const url = new URL(window.location.href);
            url.searchParams.set('circle_id', $event.detail.value);
            url.searchParams.set('date', '{{ $date }}');
            const teacherId = '{{ $selectedTeacherId ?? '' }}';
            if (teacherId) { url.searchParams.set('teacher_id', teacherId); }
            window.location.href = url.toString();
        }
    " />
                </div>
            </form>

            <div class="absolute -right-20 -bottom-20 w-80 h-80 bg-emerald-400/10 rounded-full blur-3xl"></div>
            <div class="absolute -left-20 -top-20 w-60 h-60 bg-white/5 rounded-full blur-3xl"></div>
        </div>

        @if ($selectedCircleId)
        <!-- UI Stats & Progress -->
        <div class="bg-white rounded-[2rem] p-6 shadow-sm border border-gray-100 flex flex-col md:flex-row items-center gap-8">
            <div class="flex items-center gap-4 shrink-0">
                <div class="w-14 h-14 rounded-2xl bg-emerald-50 flex items-center justify-center">
                    <span class="text-2xl font-black text-emerald-700" x-text="`${recordedCount}/${totalCount}`"></span>
                </div>
                <div>
                    <h3 class="font-black text-gray-800 text-lg">التقدم المحرز</h3>
                    <p class="text-gray-400 text-xs">تم تحضير <span x-text="recordedCount"></span> من <span x-text="totalCount"></span> طالباً</p>
                </div>
            </div>

            <div class="flex-1 w-full">
                <div class="w-full bg-gray-50 h-3 rounded-full overflow-hidden border border-gray-100 p-0.5">
                    <div class="bg-emerald-500 h-full rounded-full transition-all duration-700 ease-out shadow-sm shadow-emerald-500/20"
                        :style="`width: ${progressPercentage}%`"></div>
                </div>
            </div>

            <div x-show="isCompleted" x-transition
                class="bg-emerald-50 text-emerald-700 px-6 py-3 rounded-2xl font-bold flex items-center gap-3 animate-bounce shrink-0">
                <div class="w-5 h-5 bg-emerald-500 text-white rounded-full flex items-center justify-center">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                            d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                اكتمل التحضير لهذا اليوم
            </div>
        </div>

        <!-- Main Content Grid -->
        <form id="attendance-form" action="{{ route('attendance.store') }}" method="POST"
            class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">
            <input type="hidden" name="circle_id" value="{{ $selectedCircleId }}">

            <!-- Left Column: Toolbar & Filters -->
            <div class="lg:col-span-1 space-y-6 lg:sticky lg:top-6 order-2 lg:order-1">
                <!-- Filters -->
                <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
                    <h4 class="font-black text-gray-800 mb-6 pb-2 border-b border-gray-50 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        فرز الطلاب
                    </h4>
                    <div class="flex flex-col gap-2">
                        <button type="button" @click="filter = 'all'"
                            :class="filter === 'all' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'"
                            class="px-5 py-3 rounded-xl text-sm font-bold transition-all text-right flex justify-between items-center">
                            الكل
                            <span class="bg-white/20 px-2 rounded-md text-[10px]" x-text="totalCount"></span>
                        </button>
                        <button type="button" @click="filter = 'not_recorded'"
                            :class="filter === 'not_recorded' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'"
                            class="px-5 py-3 rounded-xl text-sm font-bold transition-all text-right flex justify-between items-center">
                            لم يتم التسجيل
                            <span class="bg-white/20 px-2 rounded-md text-[10px]" x-text="students.filter(s => s.status === 'not_recorded').length"></span>
                        </button>
                        <button type="button" @click="filter = 'present'"
                            :class="filter === 'present' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'"
                            class="px-5 py-3 rounded-xl text-sm font-bold transition-all text-right flex justify-between items-center">
                            حاضر
                            <span class="bg-white/20 px-2 rounded-md text-[10px]" x-text="students.filter(s => s.status === 'present').length"></span>
                        </button>
                        <button type="button" @click="filter = 'late'"
                            :class="filter === 'late' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'"
                            class="px-5 py-3 rounded-xl text-sm font-bold transition-all text-right flex justify-between items-center">
                            متأخر
                            <span class="bg-white/20 px-2 rounded-md text-[10px]" x-text="students.filter(s => s.status === 'late').length"></span>
                        </button>
                        <button type="button" @click="filter = 'absent'"
                            :class="filter === 'absent' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'"
                            class="px-5 py-3 rounded-xl text-sm font-bold transition-all text-right flex justify-between items-center">
                            غائب
                            <span class="bg-white/20 px-2 rounded-md text-[10px]" x-text="students.filter(s => s.status === 'absent').length"></span>
                        </button>
                    </div>
                </div>

                <!-- Actions -->
                <div class="bg-[#0b3d2c] p-6 rounded-[2rem] shadow-xl text-white">
                    <h4 class="font-black mb-6 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        إجراءات سريعة
                    </h4>
                    <div class="space-y-4">
                        <button type="button" @click="allPresent()"
                            class="w-full flex items-center justify-between p-4 rounded-2xl bg-white/10 hover:bg-white/20 transition-all font-bold group border border-white/5">
                            <span>تحضير غير المسجلين حاضر</span>
                            <div class="w-8 h-8 rounded-lg bg-emerald-500/20 flex items-center justify-center text-emerald-400 group-hover:bg-emerald-500 group-hover:text-white transition-all">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </button>

                        <!-- ✅ زر الحفظ المفقود -->
                        <button type="button" @click="submitForm()"
                            class="w-full mt-2 flex items-center justify-center gap-3 p-5 rounded-3xl bg-emerald-500 text-white shadow-lg shadow-emerald-500/20 hover:scale-[1.02] active:scale-95 transition-all font-black text-lg">
                            <span>حفظ السجل</span>
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Column: Student List -->
            <div class="lg:col-span-3 space-y-3 order-1 lg:order-2">
                <template x-for="(s, index) in filteredStudents" :key="s.id">
                    <div class="bg-white rounded-3xl p-5 md:p-6 shadow-sm border border-gray-100 flex flex-col gap-4 transition-all duration-300 group"
                        :class="{
                            'opacity-60 grayscale-[0.3] border-dashed bg-gray-50/50 scale-[0.98]': s.status === 'not_recorded',
                            'border-emerald-200 bg-emerald-50/5': s.status === 'present',
                            'border-red-200 bg-red-50/5': s.status === 'absent',
                            'border-amber-200 bg-amber-50/5': s.status === 'late',
                            'border-blue-200 bg-blue-50/5': s.status === 'excused'
                        }">

                        <!-- Row 1: Avatar, Name, Controls -->
                        <div class="flex flex-col md:flex-row items-center gap-6">
                            <!-- Avatar & Name -->
                            <div class="flex items-center gap-5 flex-1 w-full relative">
                                <div class="absolute -right-3 top-1/2 -translate-y-1/2 w-1.5 h-6 rounded-full"
                                    :class="{
                                        'bg-emerald-500': s.status === 'present',
                                        'bg-red-500': s.status === 'absent',
                                        'bg-amber-500': s.status === 'late',
                                        'bg-blue-500': s.status === 'excused',
                                        'bg-gray-300': s.status === 'not_recorded'
                                    }">
                                </div>

                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 transition-colors"
                                    :class="{
                                        'bg-emerald-100 text-emerald-600': s.status === 'present',
                                        'bg-red-100 text-red-600': s.status === 'absent',
                                        'bg-gray-100 text-gray-400': s.status === 'not_recorded'
                                    }">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <div class="text-right">
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-lg font-black transition-colors"
                                            :class="s.status === 'absent' ? 'text-red-700' : 'text-gray-800'"
                                            x-text="s.name"></h4>
                                        <div x-show="s.status === 'absent'"
                                            class="text-[10px] bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-bold">
                                            غائب</div>
                                    </div>
                                    <p class="text-gray-400 text-[10px] tabular-nums" x-text="`رقم الطالب: #${s.id}`"></p>
                                </div>
                            </div>

                            <!-- Controls -->
                            <div class="flex flex-wrap items-center justify-center gap-2">
                                <!-- Present -->
                                <button type="button" @click="s.status = 'present'"
                                    :class="s.status === 'present' ? 'bg-emerald-500 text-white border-emerald-500 shadow-lg shadow-emerald-500/20' : 'bg-white text-gray-400 border-gray-100 hover:border-emerald-200'"
                                    class="w-24 py-2.5 rounded-xl border-2 transition-all font-black text-sm flex flex-col items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="text-[10px]">حاضر</span>
                                </button>

                                <!-- Late -->
                                <button type="button" @click="s.status = 'late'"
                                    :class="s.status === 'late' ? 'bg-amber-500 text-white border-amber-500 shadow-lg shadow-amber-500/20' : 'bg-white text-gray-400 border-gray-100 hover:border-amber-200'"
                                    class="w-24 py-2.5 rounded-xl border-2 transition-all font-black text-sm flex flex-col items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-[10px]">متأخر</span>
                                </button>

                                <!-- Absent -->
                                <button type="button" @click="s.status = 'absent'"
                                    :class="s.status === 'absent' ? 'bg-red-500 text-white border-red-500 shadow-xl shadow-red-500/20' : 'bg-white text-gray-400 border-gray-100 hover:border-red-200'"
                                    class="w-24 py-2.5 rounded-xl border-2 transition-all font-black text-sm flex flex-col items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <span class="text-[10px]">غائب</span>
                                </button>

                                <!-- Excused -->
                                <button type="button" @click="s.status = 'excused'"
                                    :class="s.status === 'excused' ? 'bg-blue-500 text-white border-blue-500 shadow-lg shadow-blue-500/20' : 'bg-white text-gray-400 border-gray-100 hover:border-blue-200'"
                                    class="w-24 py-2.5 rounded-xl border-2 transition-all font-black text-sm flex flex-col items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-[10px]">بعذر</span>
                                </button>
                            </div>
                        </div>

                        <!-- ✅ Row 2: Notes + Card -->
                        <div x-show="s.status !== 'not_recorded'" x-transition class="w-full">
                            <div class="flex flex-col md:flex-row gap-3">
                                <div class="flex items-center gap-3 bg-gray-50 rounded-2xl p-3 flex-1">
                                    <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    <input type="text" x-model="s.notes" placeholder="ملاحظات (اختياري)..."
                                        class="w-full bg-transparent border-none focus:ring-0 text-sm text-gray-700 placeholder-gray-400 font-medium">
                                </div>

                                <div class="flex items-center gap-3 bg-gray-50 rounded-2xl p-3 md:w-44">
                                    <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                    </svg>
                                    <input type="number" min="0" step="1" x-model.number="s.card" placeholder="Card"
                                        class="w-full bg-transparent border-none focus:ring-0 text-sm text-gray-700 placeholder-gray-400 font-medium">
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Empty State -->
                <div x-show="filteredStudents.length === 0"
                    class="bg-white rounded-[2rem] p-16 text-center border border-dashed border-gray-200">
                    <p class="text-gray-400 font-bold">لا يوجد طلاب يطابقون هذا الفلتر حالياً</p>
                </div>
            </div>
        </form>
        @else
        <!-- No Circle Selected State -->
        <div class="bg-white rounded-[3rem] p-24 text-center border border-dashed border-gray-200 shadow-sm">
            <div class="w-24 h-24 bg-emerald-50 rounded-[2rem] flex items-center justify-center mx-auto mb-8 animate-pulse">
                <svg class="w-12 h-12 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
            </div>
            <h4 class="text-2xl font-black text-gray-800 mb-3">يرجى اختيار الحلقة لعرض سجل الحضور</h4>
            <p class="text-gray-400 font-medium">يمكنك اختيار الحلقة والتاريخ بسرعة من الشريط العلوي لبدء التحضير</p>
        </div>
        @endif
    </div>
</x-layouts.markaz-layout>