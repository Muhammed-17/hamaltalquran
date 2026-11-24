<x-layouts.markaz-layout>
<script>
        window.__students = @json($students);
        window.__csrf = '{{ csrf_token() }}';
        window.__notifyUrl = '{{ route('attendance.sequential-absences.notify', '__ID__') }}';
        // window.__notifyUrl = '{{ route('attendance.sequential-absences.notify', '__ID__') }}';
    </script>
    <div class="max-w-6xl mx-auto"
        x-data="{
            sortField: 'absence_days',
            sortAsc: false,
            students: window.__students,
            csrfToken: window.__csrf,
            notifyUrl: window.__notifyUrl,
            sendingIds: [],
            toastMessage: '',
            toastType: 'success',
            toastShow: false,
            toastTimeout: null,
            showMessageModal: false,
            selectedStudentId: null,
            selectedStudentName: '',
            customMessage: '',
            showDatesModal: false,
            selectedDatesStudentName: '',
            selectedDates: [],
            contactFilter: 'all',
            get filteredStudents() {
                if (this.contactFilter === 'contacted') {
                    return this.students.filter(s => s.is_guardian_contacted);
                }
                if (this.contactFilter === 'not_contacted') {
                    return this.students.filter(s => !s.is_guardian_contacted);
                }
                return this.students;
            },
            sortBy(field) {
                if (this.sortField === field) {
                    this.sortAsc = !this.sortAsc;
                } else {
                    this.sortField = field;
                    this.sortAsc = field === 'name';
                }
                this.sortStudents();
            },
            sortStudents() {
                this.students = [...this.students].sort((a, b) => {
                    if (this.sortField === 'name') {
                        return this.sortAsc
                            ? a.name.localeCompare(b.name)
                            : b.name.localeCompare(a.name);
                    }
                    if (this.sortField === 'is_guardian_contacted') {
                        return this.sortAsc
                            ? (a.is_guardian_contacted === b.is_guardian_contacted ? 0 : a.is_guardian_contacted ? 1 : -1)
                            : (a.is_guardian_contacted === b.is_guardian_contacted ? 0 : b.is_guardian_contacted ? 1 : -1);
                    }
                    return this.sortAsc
                         ? a.absence_days - b.absence_days
                         : b.absence_days - a.absence_days;
                 });
             },
             showToast(message, type) {
                 if (this.toastTimeout) clearTimeout(this.toastTimeout);
                 this.toastMessage = message;
                 this.toastType = type;
                 this.toastShow = true;
                 this.toastTimeout = setTimeout(() => { this.toastShow = false; }, 4000);
             },
             hideToast() {
                 this.toastShow = false;
                 if (this.toastTimeout) clearTimeout(this.toastTimeout);
             },
             openNotifyModal(studentId) {
                 const student = this.students.find(s => s.id === studentId);
                 if (!student) return;
                 this.selectedStudentId = studentId;
                 this.selectedStudentName = student.name;
                 this.customMessage = 'تم رصد ' + student.absence_days + ' أيام غياب لابنكم ' + student.name + ' خلال الشهر الحالي. يرجى ذكر سبب الغياب';
                 this.showMessageModal = true;
             },
             closeNotifyModal() {
                 this.showMessageModal = false;
                 this.selectedStudentId = null;
                 this.selectedStudentName = '';
                 this.customMessage = '';
             },
             openWhatsApp(studentId) {
                 const student = this.students.find(s => s.id === studentId);
                 if (!student || !student.whatsapp_number) {
                     this.showToast('لا يوجد رقم واتساب مسجل لهذا الطالب.', 'error');
                     return;
                 }
                 const normalized = this.normalizeWhatsAppNumber(student.whatsapp_number);
                 if (!normalized) {
                     this.showToast('رقم الواتساب غير صالح.', 'error');
                     return;
                 }
                 const message = 'تم رصد ' + student.absence_days + ' أيام غياب لابنكم ' + student.name + ' خلال الشهر الحالي. يرجى ذكر سبب الغياب';
                 const url = 'https://wa.me/' + normalized + '?text=' + encodeURIComponent(message);
                 window.open(url, '_blank');
             },
             normalizeWhatsAppNumber(raw) {
                 let digits = String(raw).replace(/\D/g, '');
                 if (!digits) return null;

                 if (digits.startsWith('020')) {
                     digits = digits.substring(1);
                 } else if (digits.startsWith('0')) {
                     digits = '20' + digits.substring(1);
                 } else if (!digits.startsWith('20')) {
                     digits = '20' + digits;
                 }

                 return (digits.length >= 10 && digits.length <= 15) ? digits : null;
             },
             openDatesModal(studentId) {
                 const student = this.students.find(s => s.id === studentId);
                 if (!student) return;
                 this.selectedDatesStudentName = student.name;
                 this.selectedDates = student.absence_dates || [];
                 this.showDatesModal = true;
             },
             closeDatesModal() {
                 this.showDatesModal = false;
                 this.selectedDatesStudentName = '';
                 this.selectedDates = [];
             },
             async confirmNotify() {
                 const studentId = this.selectedStudentId;
                 if (!studentId || this.sendingIds.includes(studentId)) return;
                 this.sendingIds.push(studentId);
                 this.closeNotifyModal();
                 try {
                      const res = await fetch(this.notifyUrl.replace('__ID__', studentId), {
                         method: 'POST',
                         headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                         body: JSON.stringify({ message: this.customMessage }),
                     });
                     const data = await res.json();
                     if (res.ok) {
                         this.showToast(data.message, 'success');
                     } else {
                         this.showToast(data.message, 'error');
                     }
                 } catch {
                     this.showToast('حدث خطأ أثناء إرسال التنبيه.', 'error');
                 } finally {
                     this.sendingIds = this.sendingIds.filter(id => id !== studentId);
                 }
             },
async toggleContact(studentId) {
    if (this.togglingIds.includes(studentId)) return;

    const student = this.students.find(s => s.id === studentId);
    if (!student) return;

    // ✅ تحديث متفائل فوري — بدون انتظار الرد من السيرفر
    const previousValue = student.is_guardian_contacted;
    const newValue = !previousValue;
    student.is_guardian_contacted = newValue;

    this.togglingIds.push(studentId);
    try {
         const res = await fetch(this.toggleUrl.replace('__ID__', studentId), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ is_guardian_contacted: newValue }), // ✅ إرسال القيمة الصريحة
        });
                     const data = await res.json();
                     if (res.ok) {
                         this.showToast(data.message, 'success');
                         if (data.is_guardian_contacted !== undefined) {
                             student.is_guardian_contacted = data.is_guardian_contacted;
                         }
                     } else {
                         student.is_guardian_contacted = previousValue;
                         this.showToast(data.message, 'error');
                     }
                 } catch {
                     student.is_guardian_contacted = previousValue;
                     this.showToast('حدث خطأ أثناء تحديث حالة التواصل.', 'error');
                 } finally {
                     this.togglingIds = this.togglingIds.filter(id => id !== studentId);
                 }
             }
         }"
        x-init="sortStudents()">

        <!-- Toast Notification -->
        <div x-show="toastShow"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            @click="hideToast()"
            class="mb-4 px-4 py-3 rounded-xl shadow-lg border cursor-pointer flex items-center gap-2 text-sm font-medium"
            :class="toastType === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-red-50 text-red-800 border-red-200'">
            <template x-if="toastType === 'success'">
                <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </template>
            <template x-if="toastType === 'error'">
                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </template>
            <span x-text="toastMessage"></span>
        </div>

        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-[#0a5c36]">الطلاب الأكثر غياباً</h1>
                <p class="text-gray-500 text-sm">الطلاب الذين غابوا 5 أيام أو أكثر خلال شهر {{ \Carbon\Carbon::parse($month.'-01')->translatedFormat('F Y') }}</p>
            </div>
            <a href="{{ route('dashboard') }}"
                class="text-sm text-blue-600 hover:text-blue-800 transition">&larr; العودة إلى لوحة التحكم</a>
        </div>

        <!-- Filters -->
        <form method="GET" action="{{ route('attendance.sequential-absences') }}"
            x-on:searchable-change.window="if (['center_id', 'circle_id'].includes($event.detail.name)) $el.submit()"
            class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6 grid grid-cols-1 md:grid-cols-6 gap-3">

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">الشهر</label>
                <input type="month" name="month" value="{{ $month }}"
                    onchange="this.form.submit()"
                    class="w-full h-11 border border-gray-200 rounded-xl px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#0a5c36]/20 focus:border-[#0a5c36]">
            </div>

            @if($centers->isNotEmpty())
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">الفرع</label>
                <x-searchable-select
                    name="center_id"
                    placeholder="الكل"
                    searchPlaceholder="بحث عن فرع..."
                    defaultOption="الكل"
                    defaultValue="{{ $selectedCenterId }}"
                    :options="$centersOptions" />
            </div>
            @endif

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">الحلقة</label>
                <x-searchable-select
                    name="circle_id"
                    placeholder="الكل"
                    searchPlaceholder="بحث عن حلقة..."
                    defaultOption="الكل"
                    defaultValue="{{ $selectedCircleId }}"
                    :options="$circlesOptions" />
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">اسم الطالب</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="ابحث بالاسم..."
                    class="w-full h-11 border border-gray-200 rounded-xl px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#0a5c36]/20 focus:border-[#0a5c36]">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">حالة التواصل</label>
                <select x-model="contactFilter"
                    class="w-full h-11 border border-gray-200 rounded-xl px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#0a5c36]/20 focus:border-[#0a5c36]">
                    <option value="all">الكل</option>
                    <option value="contacted">تم التواصل</option>
                    <option value="not_contacted">لم يتم التواصل</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit"
                    class="flex-1 h-11 bg-[#0a5c36] hover:bg-[#084b2c] text-white text-sm font-bold px-4 rounded-xl transition">
                    بحث
                </button>
                @if($selectedCenterId || $selectedCircleId || $search || $month !== now()->format('Y-m'))
                <a href="{{ route('attendance.sequential-absences') }}"
                    class="h-11 flex items-center px-3 text-sm text-gray-500 hover:text-gray-700 border border-gray-200 rounded-xl transition">
                    إعادة
                </a>
                @endif
            </div>
        </form>

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                <span class="block text-2xl font-bold text-gray-800">{{ $students->count() }}</span>
                <span class="text-sm text-gray-500">طالب متطابق مع نمط الغياب المتتالي</span>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                <span class="block text-2xl font-bold text-amber-600">{{ $students->where('is_guardian_contacted', false)->count() }}</span>
                <span class="text-sm text-gray-500">بانتظار التواصل مع ولي الأمر</span>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                <span class="block text-2xl font-bold text-emerald-600">{{ $students->avg('absence_days') ? number_format($students->avg('absence_days'), 1) : 0 }}</span>
                <span class="text-sm text-gray-500">متوسط أيام الغياب لكل طالب</span>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="text-right px-4 py-3 font-bold text-gray-600">#</th>
                            <th @click="sortBy('name')"
                                class="text-right px-4 py-3 font-bold text-gray-600 cursor-pointer select-none hover:text-gray-800">
                                اسم الطالب
                                <span x-show="sortField === 'name'" x-text="sortAsc ? '↑' : '↓'" class="mr-1"></span>
                            </th>
                            <th class="text-right px-4 py-3 font-bold text-gray-600">الحلقة</th>
                            <th @click="sortBy('absence_days')"
                                class="text-center px-4 py-3 font-bold text-gray-600 cursor-pointer select-none hover:text-gray-800">
                                أيام الغياب
                                <span x-show="sortField === 'absence_days'" x-text="sortAsc ? '↑' : '↓'" class="mr-1"></span>
                            </th>
                            @can('notify attendance')
                            <th class="text-center px-4 py-3 font-bold text-gray-600">إرسال تنبيه</th>
                            @endcan
                            <th @click="sortBy('is_guardian_contacted')"
                                class="text-center px-4 py-3 font-bold text-gray-600 cursor-pointer select-none hover:text-gray-800">
                                حالة التواصل
                                <span x-show="sortField === 'is_guardian_contacted'" x-text="sortAsc ? '↑' : '↓'" class="mr-1"></span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="(student, index) in filteredStudents" :key="student.id">
                            <tr :class="[
                                index % 2 === 0 ? 'bg-white' : 'bg-gray-50/30',
                                student.is_guardian_contacted ? 'opacity-60' : 'hover:bg-red-50/50 transition'
                            ]">
                                <td class="px-4 py-3 text-gray-500" x-text="index + 1"></td>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-gray-800" x-text="student.name"></span>
                                </td>
                                <td class="px-4 py-3 text-gray-600" x-text="student.circle?.name || '—'"></td>
                                <td class="px-4 py-3 text-center">
                                    <button @click="openDatesModal(student.id)"
                                        class="inline-block bg-red-100 hover:bg-red-200 text-red-700 text-xs px-3 py-1 rounded-full font-bold transition cursor-pointer"
                                        x-text="student.absence_days"></button>
                                </td>
                                @can('notify attendance')
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button @click="openNotifyModal(student.id)"
                                            :disabled="sendingIds.includes(student.id)"
                                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all"
                                            :class="sendingIds.includes(student.id) ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-[#0a5c36] hover:bg-[#084b2c] text-white'">
                                            <span x-show="!sendingIds.includes(student.id)">إرسال</span>
                                            <span x-show="sendingIds.includes(student.id)" class="inline-flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                                </svg>
                                                جاري
                                            </span>
                                        </button>
                                        <button x-show="student.whatsapp_number"
                                            @click="openWhatsApp(student.id)"
                                            title="فتح واتساب"
                                            class="p-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white transition-all">
                                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
                                                <path d="M12.004 2.003c-5.514 0-9.997 4.483-9.997 9.997 0 1.762.462 3.484 1.34 5.003l-1.42 5.19 5.312-1.394a9.96 9.96 0 004.765 1.213h.005c5.514 0 9.997-4.483 9.997-9.997 0-2.669-1.04-5.178-2.927-7.065a9.935 9.935 0 00-7.075-2.947zm5.836 15.798a8.298 8.298 0 01-4.836 1.567h-.004a8.29 8.29 0 01-4.223-1.155l-.303-.18-3.15.827.842-3.074-.198-.316a8.267 8.267 0 01-1.267-4.416c0-4.591 3.735-8.325 8.328-8.325 2.225 0 4.316.867 5.89 2.443a8.263 8.263 0 012.437 5.885c0 4.592-3.735 8.326-8.316 8.326z" />
                                            </svg>

                                        </button>
                                    </div>
                                </td>
                                @endcan
                                <td class="px-4 py-3 text-center">
                                    <span class="text-xs font-bold"
                                        :class="student.is_guardian_contacted ? 'text-emerald-600' : 'text-gray-400'"
                                        x-text="student.is_guardian_contacted ? 'تم التواصل' : 'لم يتم'"></span>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filteredStudents.length === 0">
                            <td colspan="{{ auth()->user()->can('notify attendance') ? 8 : 7 }}" class="px-4 py-12 text-center">
                                <div class="text-gray-400 mb-2">
                                    <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <p class="text-gray-500">لا يوجد طلاب متطابقين مع نمط الغياب المتتالي حالياً.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer info -->
        @if($students->isNotEmpty())
        <p class="text-xs text-gray-400 mt-4 text-center">
            * يتم عرض الطلاب الذين بلغ عدد أيام غيابهم 5 أيام أو أكثر خلال الشهر المحدد فقط.
        </p>
        @endif
        <!-- Message Modal -->
        <div x-show="showMessageModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
            @click.self="closeNotifyModal()">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 p-6"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-800">إرسال تنبيه</h3>
                    <button @click="closeNotifyModal()" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <p class="text-sm text-gray-500 mb-4">
                    كتابة رسالة تنبيه لولي أمر الطالب: <span class="font-medium text-gray-700" x-text="selectedStudentName"></span>
                </p>

                <textarea x-model="customMessage"
                    class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-700 resize-none focus:outline-none focus:ring-2 focus:ring-[#0a5c36]/20 focus:border-[#0a5c36] transition"
                    rows="4"
                    placeholder="اكتب رسالة التنبيه..."></textarea>
                <div class="flex justify-end gap-3 mt-4">
                    <button @click="closeNotifyModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition">
                        إلغاء
                    </button>
                    <button @click="confirmNotify()"
                        :disabled="sendingIds.includes(selectedStudentId)"
                        class="px-5 py-2 text-sm font-bold text-white bg-[#0a5c36] hover:bg-[#084b2c] rounded-xl transition disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2">
                        <span x-show="!sendingIds.includes(selectedStudentId)">إرسال التنبيه</span>
                        <span x-show="sendingIds.includes(selectedStudentId)" class="inline-flex items-center gap-1">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            جاري
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Absence Dates Modal -->
        <div x-show="showDatesModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
            @click.self="closeDatesModal()">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6 max-h-[80vh] flex flex-col"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-800">أيام الغياب</h3>
                    <button @click="closeDatesModal()" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <p class="text-sm text-gray-500 mb-4">
                    تفاصيل غياب الطالب: <span class="font-medium text-gray-700" x-text="selectedDatesStudentName"></span>
                    (<span x-text="selectedDates.length"></span> يوم)
                </p>
                <div class="overflow-y-auto flex-1 space-y-2">
                    <template x-for="(date, i) in selectedDates" :key="i">
                        <div class="flex items-center gap-3 bg-red-50 border border-red-100 rounded-xl px-4 py-2.5">
                            <svg class="w-4 h-4 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-sm font-medium text-gray-700" x-text="date"></span>
                        </div>
                    </template>
                    <div x-show="selectedDates.length === 0" class="text-center text-gray-400 text-sm py-6">
                        لا توجد تواريخ غياب مسجلة
                    </div>
                </div>
                <div class="flex justify-end mt-4">
                    <button @click="closeDatesModal()"
                        class="px-5 py-2 text-sm font-bold text-white bg-[#0a5c36] hover:bg-[#084b2c] rounded-xl transition">
                        إغلاق
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.markaz-layout>