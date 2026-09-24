@php
$isEdit = isset($student) && $student->exists;
$construction = $construction ?? ($student->constructionDetail ?? null);
$itqan = $itqan ?? ($student->itqanDetail ?? null);
$ibda = $ibda ?? ($student->ibdaDetail ?? null);

$regMonth = now()->month;
$isNextYearReg = ($regMonth >= 7 && $regMonth <= 9);

    $gradePromotion=[ 'الأول'=> 'الثاني',
    'الثاني' => 'الثالث',
    'الثالث' => 'الرابع',
    'الرابع' => 'الخامس',
    'الخامس' => 'السادس',
    'السادس' => 'لا يوجد',
    ];

    $savedGrade = isset($student) ? ($student->school_grade ?? '') : '';
    $suggestedGrade = ($isNextYearReg && !$isEdit && isset($gradePromotion[$savedGrade]))
    ? $gradePromotion[$savedGrade]
    : $savedGrade;

    $guardianData = null;
    if (isset($student) && $student->guardian_id && $student->guardian) {
    $guardianData = [
    'id' => $student->guardian->id,
    'name' => $student->guardian->name,
    'email' => $student->guardian->email ?? '',
    'is_active' => ($student->guardian->status ?? '') === 'active',
    ];
    }
    $guardianQueryName = old(
    'guardian_name',
    optional(\App\Models\User::find(old('guardian_id', isset($student) ? $student->guardian_id : '')))->name ?? ''
    );
    @endphp

    <div id="student-form" class="space-y-8">
        <!-- ───────────────── بيانات المشرف والالتحاق ───────────────── -->
        <div id="step-1" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 space-y-6">
            <div class="flex items-center gap-3 mb-6 border-b border-gray-50 pb-4">
                <div class="p-3 bg-[#e8f5ed] text-[#0a5c36] rounded-2xl text-xl">📋</div>
                <div>
                    <h2 class="text-xl font-black text-gray-800">بيانات المشرف والالتحاق</h2>
                    <p class="text-xs text-gray-400 mt-1">تحديد المشرف المسؤول ومستوى دخول الطالب المبدئي</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">
                        المشرف المقيم / مسجل البيانات <span class="text-red-500">*</span>
                    </label>

                    @if($lockedSupervisor ?? false)
                    <div class="w-full p-3 bg-gray-100 border border-gray-200 rounded-2xl text-sm font-medium text-gray-700 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                        {{ $lockedSupervisor->user?->name ?? $lockedSupervisor->name }}
                    </div>
                    <input type="hidden" name="supervisor_id" value="{{ $lockedSupervisor->id }}">
                    @else
                    <select name="supervisor_id" data-field="supervisor_id"
                        class="w-full p-3 border rounded-2xl text-sm font-medium focus:outline-none focus:ring-1 transition-all">
                        <option value="{{ old('supervisor_id', $student->supervisor_id ?? '') == '' ? 'selected' : '' }}">
                            -- اختر المشرف --
                        </option>
                        @foreach ($supervisors ?? [] as $supervisor)
                        @php
                        $roleName = $supervisor->user?->roles?->first()?->name ?? '';
                        $roleLabel = match($roleName) {
                        'admin' => 'المسؤول',
                        'general_manager' => 'المدير العام',
                        'manager' => 'مدير فرع',
                        'supervisor' => 'مشرف',
                        default => 'مشرف',
                        };
                        @endphp
                        <option value="{{ $supervisor->id }}"
                            {{ old('supervisor_id', $student->supervisor_id ?? '') == $supervisor->id ? 'selected' : '' }}>
                            {{ $supervisor->user?->name ?? $supervisor->name }} ({{ $roleLabel }})
                        </option>
                        @endforeach
                    </select>

                    @if(($supervisors ?? collect())->isEmpty())
                    <p class="text-xs text-amber-500 mt-1">لا يوجد مشرفون متاحون</p>
                    @endif
                    @endif

                    <span data-error-for="supervisor_id" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                    @error('supervisor_id')
                    <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">تاريخ المقابلة / التسجيل</label>
                    <input type="date" name="join_date" data-field="join_date"
                        value="{{ old('join_date', $student->join_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                        class="w-full p-3 border rounded-2xl text-sm font-medium focus:outline-none focus:ring-1 transition-all">
                    <span data-error-for="join_date" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                    @error('join_date')
                    <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        </div>

        <!-- ───────────────── البيانات الأساسية ───────────────── -->
        <div id="step-2" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 space-y-6">
            <div class="space-y-6">
                <div class="flex items-center gap-3 mb-6 border-b border-gray-50 pb-4">
                    <div class="p-3 bg-emerald-50 text-[#0a5c36] rounded-2xl text-xl">👤</div>
                    <div>
                        <h2 class="text-xl font-black text-[#0a5c36]">البيانات الأساسية</h2>
                        <p class="text-xs text-gray-400 mt-1">معلومات الطالب الشخصية والاتصال</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">مقدم طلب التسجيل <span class="text-red-500">*</span></label>
                    <x-creatable-select
                        name="applicant"
                        :options="['الأم', 'الأب', 'الطالب نفسه']"
                        :value="old('applicant', $student->applicant ?? '')"
                        placeholder="اختر أو اكتب مقدم الطلب..." />
                    <span data-error-for="applicant" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                    @error('applicant')
                    <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-gray-700">الرقم القومي</label>
                        <input type="text" name="student_code" data-field="student_code"
                            value="{{ old('student_code', $student->student_code ?? '') }}"
                            placeholder="14 رقم (اختياري)"
                            inputmode="numeric"
                            maxlength="14"
                            pattern="[0-9]{14}"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 14)"
                            class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all">
                        <span data-error-for="student_code" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                        @error('student_code')
                        <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-gray-700">اسم الطالب (رباعيًّا) <span class="text-red-500">*</span></label>
                        <input type="text" name="name" data-field="name" placeholder="الاسم الرباعي كاملًا"
                            value="{{ old('name', $student->name ?? '') }}"
                            class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all">
                        <span data-error-for="name" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                        @error('name')
                        <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-gray-700">النوع <span class="text-red-500">*</span></label>
                        <div class="flex gap-6 p-3 bg-gray-50 rounded-2xl border border-gray-100">
                            @foreach(['ذكر', 'أنثى'] as $genderOption)
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-600 cursor-pointer">
                                <input type="radio" name="gender" value="{{ $genderOption }}" data-field="gender"
                                    @checked(old('gender', $student->gender ?? '') == $genderOption)
                                class="text-[#0a5c36] focus:ring-[#0a5c36]" >
                                <span>{{ $genderOption }}</span>
                            </label>
                            @endforeach
                        </div>
                        <span data-error-for="gender" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                        @error('gender')
                        <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-gray-700">تاريخ الميلاد</label>
                        <input type="date" name="date_of_birth" data-field="date_of_birth"
                            value="{{ old('date_of_birth', $student->date_of_birth?->format('Y-m-d') ?? '') }}"
                            max="{{ now()->subMonths(30)->format('Y-m-d') }}"
                            class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all">
                        <span data-error-for="date_of_birth" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                        @error('date_of_birth')
                        <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-gray-700">العنوان التفصيلي <span class="text-red-500">*</span></label>
                        <input type="text" name="address" data-field="address"
                            value="{{ old('address', $student->address ?? '') }}"
                            placeholder="مثال: الشرقية - ههيا - قرية صبيح"
                            class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all">
                        <span data-error-for="address" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                        @error('address')
                        <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-gray-700">المركز فرع <span class="text-red-500">*</span></label>
                        <select name="center_id" class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all appearance-none">
                            @foreach($centers ?? [] as $center)
                            <option value="{{ $center->id }}"
                                @selected(old('center_id', $student->center_id ?? '') == $center->id)>
                                {{ $center->name }}
                            </option>
                            @endforeach
                        </select>
                        <span data-error-for="center_id" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                        @error('center_id')
                        <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="border-t border-gray-100 my-6"></div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-gray-700">رقم الواتساب للمتابعة</label>
                        <div class="flex gap-2">
                            <input type="tel" name="whatsapp_number" data-field="whatsapp_number"
                                value="{{ old('whatsapp_number', $student->whatsapp_number ?? '') }}"
                                id="whatsappInput"
                                placeholder="01xxxxxxxxx"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                class="flex-1 p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all">
                            <button type="button" onclick="checkWhatsappNumber('whatsappInput')"
                                class="shrink-0 px-4 py-3 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-2xl text-sm font-bold transition-colors flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
                                    <path d="M12.001 2C6.478 2 2 6.478 2 12c0 1.85.499 3.583 1.365 5.075L2 22l5.075-1.325A9.955 9.955 0 0012.001 22C17.523 22 22 17.523 22 12S17.523 2 12.001 2zm0 18.156a8.148 8.148 0 01-4.158-1.138l-.298-.177-3.095.808.826-3.02-.194-.31A8.146 8.146 0 013.844 12c0-4.5 3.657-8.156 8.157-8.156 4.5 0 8.156 3.656 8.156 8.156 0 4.5-3.656 8.156-8.156 8.156z" />
                                </svg>
                                تحقق
                            </button>
                        </div>
                        <span data-error-for="whatsapp_number" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                        @error('whatsapp_number')
                        <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-gray-700">رقم اتصال إضافي</label>
                        <div class="flex gap-2">
                            <input type="tel" name="second_phone" data-field="second_phone"
                                value="{{ old('second_phone', $student->second_phone ?? '') }}"
                                id="secondPhoneInput"
                                placeholder="01xxxxxxxxx"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                class="flex-1 p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all">
                            <button type="button" onclick="checkWhatsappNumber('secondPhoneInput')"
                                class="shrink-0 px-4 py-3 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-2xl text-sm font-bold transition-colors flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
                                    <path d="M12.001 2C6.478 2 2 6.478 2 12c0 1.85.499 3.583 1.365 5.075L2 22l5.075-1.325A9.955 9.955 0 0012.001 22C17.523 22 22 17.523 22 12S17.523 2 12.001 2zm0 18.156a8.148 8.148 0 01-4.158-1.138l-.298-.177-3.095.808.826-3.02-.194-.31A8.146 8.146 0 013.844 12c0-4.5 3.657-8.156 8.157-8.156 4.5 0 8.156 3.656 8.156 8.156 0 4.5-3.656 8.156-8.156 8.156z" />
                                </svg>
                                تحقق
                            </button>
                        </div>
                        <span data-error-for="second_phone" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                        @error('second_phone')
                        <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-gray-700">صاحب رقم الواتساب</label>
                        <x-creatable-select
                            name="whatsapp_owner"
                            :options="['الأم', 'الأب', 'الطالب نفسه']"
                            :value="old('whatsapp_owner', $student->whatsapp_owner ?? '')"
                            placeholder="اختر أو اكتب..." />
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-gray-700">صاحب الرقم الإضافي</label>
                        <x-creatable-select
                            name="additional_contact_owner"
                            :options="['الأم', 'الأب', 'الطالب نفسه']"
                            :value="old('additional_contact_owner', $student->additional_contact_owner ?? '')"
                            placeholder="اختر أو اكتب..." />
                    </div>
                </div>

                {{-- ───── ولي الأمر ───── --}}
                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">
                        تحديد ولي الأمر <span class="text-red-500">*</span>
                    </label>

                    <div class="relative" x-data="guardianSearch()">

                        {{-- حقل البحث --}}
                        <div class="relative">
                            <input type="text" id="guardianSearchInput"
                                x-model="query"
                                @input.debounce.400ms="search()"
                                @focus="if(query.length >= 2) search()"
                                @click.outside="results = []"
                                placeholder="ابحث بالاسم، الهاتف، الإيميل، أو رقم الحساب..."
                                autocomplete="off"
                                class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all">

                            {{-- مؤشر التحميل --}}
                            <div x-show="searching" class="absolute left-3 top-1/2 -translate-y-1/2">
                                <svg class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                </svg>
                            </div>
                        </div>

                        {{-- نتائج البحث --}}
                        {{-- نتائج البحث --}}
                        <div x-show="results.length > 0"
                            class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-2xl shadow-lg overflow-hidden">
                            <template x-for="guardian in results" :key="guardian.id">
                                <div class="px-4 py-3 flex justify-between items-center border-b border-gray-50 last:border-0 hover:bg-emerald-50 transition-colors">

                                    {{-- معلومات ولي الأمر --}}
                                    <div class="flex items-center gap-2 flex-1 min-w-0">
                                        <span class="text-xs font-mono bg-gray-100 text-gray-500 px-2 py-0.5 rounded-lg shrink-0"
                                            x-text="'#' + guardian.id"></span>
                                        <div class="flex flex-col min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-bold text-gray-800 text-sm truncate" x-text="guardian.name"></span>
                                                <span x-show="guardian.is_active"
                                                    class="text-xs bg-emerald-100 text-emerald-600 px-1.5 py-0.5 rounded-md font-medium shrink-0">نشط</span>
                                                <span x-show="!guardian.is_active"
                                                    class="text-xs bg-orange-100 text-orange-600 px-1.5 py-0.5 rounded-md font-medium shrink-0">غير نشط</span>
                                            </div>
                                            <div class="flex items-center gap-3 mt-0.5">
                                                <span class="text-gray-400 text-xs" x-show="guardian.email"
                                                    x-text="'✉️ ' + guardian.email"></span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- زر الاختيار --}}
                                    <button type="button"
                                        @click.stop="select(guardian)"
                                        class="shrink-0 mr-3 inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#0a5c36] hover:bg-[#084a2b] text-white text-xs font-semibold rounded-xl transition-colors shadow-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        اختيار
                                    </button>

                                </div>
                            </template>
                        </div>

                        {{-- ✅ رسالة لا نتائج --}}
                        <div x-show="noResults && !searching && query.length >= 2"
                            class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-2xl shadow-lg p-3 text-sm text-gray-500 text-center">
                            لم يُعثر على ولي أمر — يمكنك إضافة حساب جديد
                        </div>

                        {{-- ولي الأمر المختار --}}
                        <div x-show="selected"
                            class="mt-2 px-3 py-2 bg-emerald-50 border border-emerald-100 rounded-xl flex justify-between items-center">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-mono bg-emerald-100 text-emerald-600 px-2 py-0.5 rounded-lg"
                                    x-text="selected?.id ? '#' + selected.id : ''"></span>
                                <span class="text-emerald-700 text-sm font-bold"
                                    x-text="'✅ ' + (selected?.name ?? '')"></span>
                                {{-- ✅ تحذير إذا الحساب غير نشط --}}
                                <span x-show="selected?.is_active === false"
                                    class="text-xs bg-orange-100 text-orange-600 px-1.5 py-0.5 rounded-md font-medium">
                                    ⚠️ غير نشط
                                </span>
                                <template x-if="selected?.email">
                                    <span class="text-emerald-400 text-xs"
                                        x-text="'· 🔑 ' + selected.email"></span>
                                </template>
                            </div>
                            <button type="button" @click="clear()"
                                class="text-gray-400 hover:text-red-500 text-xs mr-2">✕ تغيير</button>
                        </div>

                        {{-- أزرار الإضافة --}}
                        <div x-show="!selected" class="mt-2 flex gap-4">
                            <button type="button" @click="createNew()"
                                class="text-sm text-emerald-600 font-bold hover:underline">
                                + إضافة ولي أمر جديد
                            </button>
                            <button type="button" @click="skipGuardian()"
                                class="text-sm text-gray-500 font-bold hover:underline">
                                تسجيل بدون ولي أمر حاليًا
                            </button>
                        </div>
                    </div>

                    {{-- hidden input --}}
                    <input type="hidden" name="guardian_id" id="guardianIdInput"
                        value="{{ old('guardian_id', $student->guardian_id ?? '') }}">

                    <span data-error-for="guardian_id" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                    @error('guardian_id')
                    <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                    @enderror

                    {{-- حقول إنشاء حساب جديد --}}
                    <div id="newGuardianFields" class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2" style="display:none;">

                        {{-- ✅ تنبيه وجود حساب مطابق --}}
                        <div id="guardianExistsAlert" class="md:col-span-2 hidden">
                            <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-700 font-medium">
                                <button type="button" onclick="useExistingGuardian()"
                                    class="underline font-bold">استخدامه بدلاً من إنشاء جديد</button>
                            </div>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <label class="block text-sm font-bold text-gray-700">
                                اسم ولي الأمر <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="guardian_name" id="guardianNameInput" data-field="guardian_name"
                                value="{{ old('guardian_name') }}"
                                placeholder="اسم ولي الأمر كاملًا"
                                class="w-full p-3 bg-gray-50 border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all">
                            <span data-error-for="guardian_name" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                            @error('guardian_name')
                            <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-gray-700">
                                البريد الإلكتروني <span class="text-red-500">*</span>
                            </label>
                            {{-- ✅ إضافة @blur لفحص وجود الحساب --}}
                            <input type="email" name="parent_email" id="parentEmailInput"
                                value="{{ old('parent_email') }}"
                                placeholder="example@email.com"
                                onblur="checkGuardianExists()"
                                class="w-full p-3 bg-gray-50 border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all">
                            {{-- ✅ نتيجة الفحص --}}
                            <div id="emailCheckResult" class="hidden text-xs font-medium mt-1"></div>
                            @error('parent_email')
                            <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-gray-700">كلمة المرور</label>
                            {{-- ✅ بدون value لمنع ظهورها في الـ source --}}
                            <input type="password" name="password" id="passwordInput"
                                placeholder="اتركها فارغة للتوليد التلقائي"
                                class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all">
                            @error('password')
                            <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ───────────────── البيانات الدراسية ───────────────── -->
        <div id="step-3" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 space-y-6">
            <div class="flex items-center gap-3 mb-6 border-b border-gray-50 pb-4">
                <div class="p-3 bg-emerald-50 text-[#0a5c36] rounded-2xl text-xl">🎓</div>
                <div>
                    <h2 class="text-xl font-black text-[#0a5c36]">البيانات الدراسية</h2>
                    <p class="text-xs text-gray-400 mt-1">المرحلة والمؤسسة التعليمية الحالية</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">المرحلة الدراسية <span class="text-red-500">*</span></label>
                    <select name="educational_stage" data-field="educational_stage"
                        class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all appearance-none">
                        <option value="" @selected(old('educational_stage', $student->educational_stage ?? '') == '')>-- اختر المرحلة --</option>
                        @foreach(['تمهيدي', 'حضانة', 'ابتدائي', 'اعدادي', 'ثانوي', 'جامعي', 'خريج'] as $stage)
                        <option value="{{ $stage }}" @selected(old('educational_stage', $student->educational_stage ?? '') == $stage)>
                            {{ $stage }}
                        </option>
                        @endforeach
                    </select>
                    <span data-error-for="educational_stage" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                    @error('educational_stage')
                    <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">نوع التعليم <span class="text-red-500">*</span></label>
                    <select name="education_type" data-field="education_type"
                        class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all appearance-none">
                        <option value="" @selected(in_array(old('education_type', $student->education_type ?? ''), ['', null, 'غير محدد']))>-- اختر النوع --</option>
                        <option value="غير محدد" @selected(old('education_type', $student->education_type ?? '') == 'غير محدد')>غير محدد</option>
                        <option value="أزهري" @selected(old('education_type', $student->education_type ?? '') == 'أزهري')>أزهري</option>
                        <option value="عام (تربية وتعليم)" @selected(old('education_type', $student->education_type ?? '') == 'عام (تربية وتعليم)')>عام (تربية وتعليم)</option>
                    </select>
                    <span data-error-for="education_type" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                    @error('education_type')
                    <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">الصف الدراسي <span class="text-red-500">*</span></label>
                    @if($isNextYearReg && !$isEdit && isset($gradePromotion[$savedGrade]))
                    <div class="mb-1 text-xs text-amber-600 font-semibold bg-amber-50 px-3 py-1.5 rounded-xl border border-amber-100">
                        ⚠️ موسم تسجيل — الصف المقترح للعام الجديد: {{ $gradePromotion[$savedGrade] }}
                    </div>
                    @endif
                    <select name="school_grade" data-field="school_grade"
                        class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all appearance-none">
                        <option value="" @selected(old('school_grade', $suggestedGrade)=='' )>-- اختر الصف --</option>
                        @foreach(['لا يوجد', 'الأول', 'الثاني', 'الثالث', 'الرابع', 'الخامس', 'السادس', 'دراسات عليا'] as $grade)
                        <option value="{{ $grade }}" @selected(old('school_grade', $suggestedGrade)==$grade)>{{ $grade }}</option>
                        @endforeach
                    </select>
                    <span data-error-for="school_grade" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                    @error('school_grade')
                    <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">المؤسسة التعليمية <span class="text-red-500">*</span></label>
                    <input type="text" name="previous_school" data-field="previous_school"
                        value="{{ old('previous_school', $student->previous_school ?? '') }}"
                        placeholder="اسم المؤسسة التعليمية بالكامل"
                        class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all">
                    <span data-error-for="previous_school" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                    @error('previous_school')
                    <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        </div>

        <!-- ───────────────── بيانات الرعاية الطلابية ───────────────── -->
        <div id="step-4" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 space-y-6">
            <div class="flex items-center gap-3 mb-6 border-b border-gray-50 pb-4">
                <div class="p-3 bg-emerald-50 text-[#0a5c36] rounded-2xl text-xl">💚</div>
                <div>
                    <h2 class="text-xl font-black text-[#0a5c36]">بيانات الرعاية الطلابية</h2>
                    <p class="text-xs text-gray-400 mt-1">الحالة الصحية، السلوكية، والسمات الشخصية</p>
                </div>
            </div>


            <div class="space-y-2">
                <label class="block text-sm font-bold text-gray-700">الحالة الصحية للطالب <span class="text-red-500">*</span></label>
                <x-creatable-select
                    name="health_status"
                    :options="['طبيعية (الحمد الله)']"
                    :value="old('health_status', $student->health_status ?? '')"
                    placeholder="اختر أو اكتب الحالة الصحية..." />
                <span data-error-for="health_status" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                @error('health_status')
                <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>

            <div class="space-y-2">
                <label class="block text-sm font-bold text-gray-700">صعوبات التعلم <span class="text-red-500">*</span></label>
                <x-creatable-select
                    name="learning_difficulties"
                    :options="['لا يوجد (الحمد الله)']"
                    :value="old('learning_difficulties', $student->learning_difficulties ?? '')"
                    placeholder="اختر أو اكتب صعوبات التعلم..." />
                <span data-error-for="learning_difficulties" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                @error('learning_difficulties')
                <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>

            <div class="space-y-2">
                <label class="block text-sm font-bold text-gray-700">السمات الشخصية <span class="text-red-500">*</span></label>
                <x-creatable-select
                    name="personal_traits"
                    :options="['لا يوجد']"
                    :value="old('personal_traits', $student->personal_traits ?? '')"
                    placeholder="اختر أو اكتب السمات الشخصية..." />
                <span data-error-for="personal_traits" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                @error('personal_traits')
                <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>

            <div class="space-y-2">
                <label class="block text-sm font-bold text-gray-700">الهواية المفضلة <span class="text-red-500">*</span></label>
                @php
                $savedHobbies = old('hobbies', $student->hobbies ?? []);
                if (is_string($savedHobbies)) $savedHobbies = json_decode($savedHobbies, true) ?? [];
                @endphp
                <x-creatable-select
                    name="hobbies"
                    :multiple="true"
                    :options="['كرة القدم', 'الكاراتيه', 'الرسم', 'البرمجة والألعاب الإلكترونية', 'الأشغال اليدوية', 'القراءة والإطلاع']"
                    :value="$savedHobbies"
                    placeholder="اختر أو اكتب هوايات..." />
                <span data-error-for="hobbies" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                @error('hobbies')
                <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>

            <div class="space-y-2">
                <label class="block text-sm font-bold text-gray-700">حالة خروج الطالب من المركز <span class="text-red-500">*</span></label>
                <x-creatable-select
                    name="student_exit_status"
                    :options="['بمفرده', 'مع ولي الأمر أو أحد الأقارب']"
                    :value="old('student_exit_status', $student->student_exit_status ?? '')"
                    placeholder="اختر أو اكتب حالة الخروج..." />
                <span data-error-for="student_exit_status" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                @error('student_exit_status')
                <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div" x-data="{ selectedLevel: '{{ old('center_entry_level', $student->center_entry_level ?? 'construction') }}', studySystem: '{{ old('study_system', $construction->study_system ?? 'group') }}' }">
            <!-- ───────────────── تقييم التلاوة ───────────────── -->
            <div id="step-5" class="bg-white rounded-3xl shadow-sm border border-gray-100 mb-8 p-8 space-y-6">
                <div class="flex items-center gap-3 mb-6 border-b border-gray-50 pb-4">
                    <div class="p-3 bg-[#e8f5ed] text-[#0a5c36] rounded-2xl text-xl">🎤</div>
                    <div>
                        <h2 class="text-xl font-black text-gray-800">تقييم التلاوة وتحديد مستوى الالتحاق</h2>
                        <p class="text-xs text-gray-400 mt-1">تحديد المسار الفني والتعليمي للطالب بناء على تقييم الشيخ المختبر</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">مستوى القراءة من المصحف <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 p-4 bg-gray-50 rounded-2xl border border-gray-100">
                        @foreach([
                        ['value' => 'مبتدئ', 'desc' => 'لا يقرأ'],
                        ['value' => 'مقبول', 'desc' => 'يقرأ ببطء'],
                        ['value' => 'متمكن', 'desc' => 'بدون أحكام'],
                        ['value' => 'متقن', 'desc' => 'توجد أحكام'],
                        ] as $readingOption)
                        <label class="flex items-center gap-2 p-3 bg-white border border-gray-200 rounded-xl cursor-pointer hover:border-[#0a5c36]/50 transition-all text-sm font-semibold text-gray-600">
                            <input type="radio" name="reading" value="{{ $readingOption['value'] }}"
                                data-field="reading"
                                @checked(old('reading', $student->reading ?? '') == $readingOption['value'])
                            class="text-[#0a5c36] focus:ring-[#0a5c36]">
                            <span>{{ $readingOption['value'] }} <span class="text-xs text-gray-400 font-normal">({{ $readingOption['desc'] }})</span></span>
                        </label>
                        @endforeach
                    </div>
                    <span data-error-for="reading" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                    @error('reading')
                    <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                <div class="space-y-4 pt-2">
                    <label class="block text-sm font-bold text-gray-700">
                        اختر مستوى تحضير أو التحاق الطالب بعد الاختبار <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <label class="flex flex-col p-4 border rounded-2xl cursor-pointer hover:border-[#0a5c36] transition-all border-gray-100 bg-gray-50">
                            <div class="flex items-center gap-2 font-bold text-[#0a5c36]">
                                <input type="radio" name="center_entry_level" value="construction"
                                    x-model="selectedLevel" data-field="center_entry_level"
                                    class="text-[#0a5c36] focus:ring-[#0a5c36]">
                                <span>🌱 مستوى البناء</span>
                            </div>
                            <span class="text-xs text-gray-500 mt-2 mr-5">الحلقات التأسيسية وحفظ الأجزاء المنتظمة</span>
                        </label>
                        <label class="flex flex-col p-4 border rounded-2xl cursor-pointer hover:border-[#7a6020] transition-all border-gray-100 bg-gray-50">
                            <div class="flex items-center gap-2 font-bold text-[#7a6020]">
                                <input type="radio" name="center_entry_level" value="mastery"
                                    x-model="selectedLevel" data-field="center_entry_level"
                                    class="text-[#b8973a] focus:ring-[#b8973a]">
                                <span>⭐ مستوى الإتقان</span>
                            </div>
                            <span class="text-xs text-gray-500 mt-2 mr-5">حلقات التثبيت، المراجعة المكثفة والخاتمين</span>
                        </label>
                        <label class="flex flex-col p-4 border rounded-2xl cursor-pointer hover:border-indigo-600 transition-all border-gray-100 bg-gray-50">
                            <div class="flex items-center gap-2 font-bold text-indigo-800">
                                <input type="radio" name="center_entry_level" value="creativity"
                                    x-model="selectedLevel" data-field="center_entry_level"
                                    class="text-indigo-600 focus:ring-indigo-500">
                                <span>🏆 مستوى الإبداع</span>
                            </div>
                            <span class="text-xs text-gray-500 mt-2 mr-5">مجالس الإجازات، القراءات والسند المتصل</span>
                        </label>
                    </div>
                    <span data-error-for="center_entry_level" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                    @error('center_entry_level')
                    <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <!-- ───────────────── مستوى البناء ───────────────── -->
            <div id="step-6" x-show="selectedLevel === 'construction'" x-transition
                class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 space-y-6">
                <div class="flex items-center gap-3 mb-6 border-b border-gray-50 pb-4">
                    <div class="p-3 bg-emerald-50 text-[#0a5c36] rounded-2xl text-xl">🌱</div>
                    <div>
                        <h2 class="text-xl font-black text-[#0a5c36]">مستوى البناء</h2>
                        <p class="text-xs text-gray-400 mt-1">تسكين الطالب في الحلقات وخطة الحفظ</p>
                    </div>
                </div>

                {{-- نظام الدراسة --}}
                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">نظام الدراسة <span class="text-red-500">*</span></label>
                    <div class="flex gap-6 p-3 bg-gray-50 rounded-2xl border border-gray-100">
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-600 cursor-pointer">
                            <input type="radio" name="study_system" value="group" x-model="studySystem" data-field="study_system"
                                @checked(old('study_system', $construction->study_system ?? 'group') == 'group')>
                            <span>جماعي</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-600 cursor-pointer">
                            <input type="radio" name="study_system" value="individual" x-model="studySystem" data-field="study_system"
                                @checked(old('study_system', $construction->study_system ?? '') == 'individual')>
                            <span>فردي</span>
                        </label>
                    </div>
                    <span data-error-for="study_system" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                    @error('study_system')
                    <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                {{-- الحلقة --}}
                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">الحلقة <span class="text-red-500">*</span></label>
                    <select name="circle_id" id="circleSelect" data-field="circle_id"
                        class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all appearance-none">
                        <option value="" @selected(old('circle_id', $construction->circle_id ?? '') == '')>-- اختر الحلقة --</option>
                        @foreach($circles as $circle)
                        <option value="{{ $circle->id }}" data-type="{{ $circle->type }}" data-url="{{ $circle->url }}"
                            @selected(old('circle_id', $construction->circle_id ?? '') == $circle->id)>
                            {{ $circle->name }}
                        </option>
                        @endforeach
                    </select>
                    <span data-error-for="circle_id" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                    @error('circle_id')
                    <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                {{-- بطاقة معلومات خطة الحلقة الجماعية (عرض فقط، تساعد المشرف على القرار) --}}
                <div id="groupPlanInfo" class="hidden bg-blue-50 border border-blue-100 rounded-2xl p-4 space-y-2">
                    <p class="text-sm font-bold text-blue-700">📋 خطة الحفظ الحالية لهذه الحلقة:</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-blue-800">
                        <p>السورة الحالية: <span id="groupPlanSurah" class="font-bold"></span></p>
                        <p>خطة الحفظ الجديد: <span id="groupPlanNew" class="font-bold"></span></p>
                        <p>خطة المراجعة: <span id="groupPlanRevision" class="font-bold"></span></p>
                        <p>خطة الحفظ القديم: <span id="groupPlanOld" class="font-bold"></span></p>
                    </div>
                    <p class="text-xs text-blue-500 mt-1">تأكد من مناسبة مستوى الحلقة لمستوى الطالب قبل التسكين. سيتم ربط الطالب بهذه الخطة تلقائيًا.</p>
                </div>

                <div id="groupPlanEmpty" class="hidden bg-amber-50 border border-amber-100 rounded-2xl p-4 text-sm text-amber-700 font-semibold">
                    ⚠️ لا توجد بيانات خطة مسجلة لهذه الحلقة بعد. سيتم إنشاء خطة الطالب كأول سجل للحلقة.
                </div>

                {{-- خطط الفردي فقط: السورة + الخطط الثلاثة (تظهر وتُملأ يدويًا) --}}
                <div x-show="studySystem === 'individual'" x-transition class="space-y-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-gray-700">السورة الحالية</label>
                        <select name="current_surah_id" data-field="current_surah_id"
                            class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all appearance-none">
                            <option value="" @selected(old('current_surah_id', $construction->current_surah_id ?? '') == '')>-- اختر السورة --</option>
                            @foreach($surahs ?? [] as $surah)
                            <option value="{{ $surah->id }}" @selected(old('current_surah_id', $construction->current_surah_id ?? '') == $surah->id)>
                                {{ $surah->number }}. {{ $surah->name_arabic }}
                            </option>
                            @endforeach
                        </select>
                        <span data-error-for="current_surah_id" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                        @error('current_surah_id')
                        <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-gray-700">خطة الحفظ الجديد <span class="text-red-500">*</span></label>
                            <input type="text" name="new_memorization_plan" data-field="new_memorization_plan"
                                value="{{ old('new_memorization_plan', $construction->new_memorization_plan ?? '') }}"
                                placeholder="مثال: 5 سطور يومياً"
                                class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all">
                            <span data-error-for="new_memorization_plan" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                            @error('new_memorization_plan')
                            <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-gray-700">خطة المراجعة <span class="text-red-500">*</span></label>
                            <input type="text" name="revision_plan" data-field="revision_plan"
                                value="{{ old('revision_plan', $construction->revision_plan ?? '') }}"
                                placeholder="مثال: وجه يومياً"
                                class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all">
                            <span data-error-for="revision_plan" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                            @error('revision_plan')
                            <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-gray-700">خطة الحفظ القديم <span class="text-red-500">*</span></label>
                            <input type="text" name="old_memorization_plan" data-field="old_memorization_plan"
                                value="{{ old('old_memorization_plan', $construction->old_memorization_plan ?? '') }}"
                                placeholder="مثال: حزب أسبوعياً"
                                class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all">
                            <span data-error-for="old_memorization_plan" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                            @error('old_memorization_plan')
                            <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- تقييم التسكين --}}
                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">تقييم التسكين</label>
                    <textarea name="placement_evaluation" data-field="placement_evaluation" rows="3"
                        placeholder="نتائج تقييم التسكين..."
                        class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all">{{ old('placement_evaluation', $construction->placement_evaluation ?? '') }}</textarea>
                </div>
            </div>

            <!-- ───────────────── مستوى الإتقان ───────────────── -->
            <div id="step-7" x-show="selectedLevel === 'mastery'" x-transition
                class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 space-y-6">
                <div class="flex items-center gap-3 mb-6 border-b border-gray-50 pb-4">
                    <div class="p-3 bg-amber-50 text-amber-600 rounded-2xl text-xl">⭐</div>
                    <div>
                        <h2 class="text-xl font-black text-amber-600">مستوى الإتقان</h2>
                        <p class="text-xs text-gray-400 mt-1">تفاصيل الحفظ والمراجعة للمتقدمين المتميزين</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-gray-700">جهة الحفظ السابقة <span class="text-red-500">*</span></label>
                        <input type="text" name="previous_memorization_side" data-field="previous_memorization_side"
                            value="{{ old('previous_memorization_side', $itqan->previous_memorization_side ?? '') }}"
                            placeholder="اسم المسجد، المركز، أو الشيخ السابق"
                            class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all">
                        <span data-error-for="previous_memorization_side" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                        @error('previous_memorization_side')
                        <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-gray-700">عدد الختمات السابقة <span class="text-red-500">*</span></label>
                        <input type="text" name="previous_khatamat_count" data-field="previous_khatamat_count"
                            value="{{ old('previous_khatamat_count', $itqan->previous_khatamat_count ?? '') }}"
                            placeholder="مثال: ختمة واحدة أو أكثر"
                            class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all">
                        <span data-error-for="previous_khatamat_count" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                        @error('previous_khatamat_count')
                        <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">مقدار المراجعة الحالي <span class="text-red-500">*</span></label>
                    <input type="text" name="current_review_amount" data-field="current_review_amount"
                        value="{{ old('current_review_amount', $itqan->current_review_amount ?? '') }}"
                        placeholder="مثال: جزء يوميًّا، حزب، نصف جزء..."
                        class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all">
                    <span data-error-for="current_review_amount" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                    @error('current_review_amount')
                    <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">تقييم مستوى الحفظ (1-10) <span class="text-red-500">*</span></label>
                    <select name="self_evaluation" data-field="self_evaluation"
                        class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all appearance-none">
                        <option value="">-- اختر التقييم --</option>
                        @for ($i = 1; $i <= 10; $i++)
                            <option value="{{ $i }}" @selected(old('self_evaluation', $itqan?->self_evaluation ?? 0) == $i)>{{ $i }}</option>
                            @endfor
                    </select>
                    <span data-error-for="self_evaluation" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                    @error('self_evaluation')
                    <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">متون التجويد المحفوظة <span class="text-red-500">*</span></label>
                    <x-creatable-select
                        name="tajweed_matn"
                        :options="['لا يوجد', 'تحفة الأطفال', 'المقدمة الجزرية']"
                        :value="old('tajweed_matn', $itqan->tajweed_matn ?? '')"
                        placeholder="اختر أو اكتب متن التجويد..." />
                    <span data-error-for="tajweed_matn" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                    @error('tajweed_matn')
                    <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-gray-700">المسار المرغوب فيه <span class="text-red-500">*</span></label>
                        <x-creatable-select
                            name="desired_path"
                            :options="['تثبيت الحفظ', 'تصحيح التلاوة', 'الإجازة والسند']"
                            :value="old('desired_path', $itqan->desired_path ?? '')"
                            placeholder="اختر أو اكتب المسار المرغوب..." />
                        <span data-error-for="desired_path" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                        @error('desired_path')
                        <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-gray-700">الوقت المناسب للمجلس <span class="text-red-500">*</span></label>
                        <x-creatable-select
                        name="preferred_time_mastery"
                        :options="['صباحًا', 'ظهرًا', 'عصرًا', 'ليلًا', 'أون لاين']"
                        :value="old('preferred_time_mastery', $itqan->preferred_time ?? '')"
                        placeholder="اختر أو اكتب الوقت المناسب..." />
                        <span data-error-for="preferred_time_mastery" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                        @error('preferred_time_mastery')
                        <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                
                <label class="block mb-2 text-sm font-bold text-gray-700">المعلم <span class="text-red-500">*</span></label>
                <select name="teacher_name" data-field="teacher_name"
                    x-bind:disabled="selectedLevel !== 'mastery'"
                    class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all appearance-none">
                    <option value="" @selected(old('teacher_name', $itqan->teacher_name ?? '') == '')>-- اختر المعلم --</option>
                    <option value="بدون تحديد" @selected(old('teacher_name', $itqan->teacher_name ?? '') == 'بدون تحديد')>بدون تحديد (حسب المتاح)</option>
                    @foreach ($teachers ?? [] as $teacherItem)
                    <option value="{{ $teacherItem->name }}" @selected(old('teacher_name', $itqan->teacher_name ?? '') == $teacherItem->name)>
                        {{ $teacherItem->name }}
                    </option>
                    @endforeach
                </select>
                <span data-error-for="teacher_name" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                @error('teacher_name')
                <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>

            <!-- ───────────────── مستوى الإبداع ───────────────── -->
            <div id="step-8" x-show="selectedLevel === 'creativity'" x-transition
                class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 space-y-6">
                <div class="flex items-center gap-3 mb-6 border-b border-gray-50 pb-4">
                    <div class="p-3 bg-indigo-50 text-indigo-600 rounded-2xl text-xl">🏆</div>
                    <div>
                        <h2 class="text-xl font-black text-indigo-600">مستوى الإبداع</h2>
                        <p class="text-xs text-gray-400 mt-1">بيانات الروايات والأسانيد التي حصل عليها الطالب</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">الإجازات والأسانيد السابقة <span class="text-red-500">*</span></label>
                    <textarea name="previous_licenses_and_chains" data-field="previous_licenses_and_chains"
                        placeholder="يرجى ذكر الإجازات، اسم الشيخ المجيز، والمتن..."
                        class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all min-h-25">{{ old('previous_licenses_and_chains', $ibda->previous_licenses_and_chains ?? '') }}</textarea>
                    <span data-error-for="previous_licenses_and_chains" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                    @error('previous_licenses_and_chains')
                    <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">المسار والرواية المراد دراستها <span class="text-red-500">*</span></label>
                    <input type="text" name="desired_narration_and_path" data-field="desired_narration_and_path"
                        value="{{ old('desired_narration_and_path', $ibda->desired_narration_and_path ?? '') }}"
                        placeholder="مثال: رواية ورش عن نافع، القراءات العشر..."
                        class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all">
                    <span data-error-for="desired_narration_and_path" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                    @error('desired_narration_and_path')
                    <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">الوقت المناسب للمجلس <span class="text-red-500">*</span></label>
                    <x-creatable-select
                    name="preferred_time_creativity"
                    :options="['صباحًا', 'ظهرًا', 'عصرًا', 'ليلًا', 'أون لاين']"
                    :value="old('preferred_time_creativity', $ibda->preferred_time ?? '')"
                    placeholder="اختر أو اكتب الوقت المناسب..." />
                    <span data-error-for="preferred_time_creativity" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                    @error('preferred_time_creativity')
                    <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                    
                    <label class="block text-sm font-bold text-gray-700">المعلم <span class="text-red-500">*</span></label>
                    <select name="supervisor_name" data-field="supervisor_name"
                        x-bind:disabled="selectedLevel !== 'creativity'"
                        class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all appearance-none">
                        <option value="">-- اختر المعلم --</option>
                        @foreach ($teachers ?? [] as $teacherItem)
                        <option value="{{ $teacherItem->name }}" @selected(old('supervisor_name', $ibda->supervisor_name ?? '') == $teacherItem->name)>
                            {{ $teacherItem->name }}
                        </option>
                        @endforeach
                    </select>
                    <span data-error-for="supervisor_name" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                    @error('supervisor_name')
                    <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>
            </div>
    </div>

    <!-- ───────────────── التوصيات النهائية ───────────────── -->
    <div id="step-9" class="bg-white rounded-3xl shadow-sm border border-gray-100 mt-8 p-8 space-y-6">
        <div class="flex items-center gap-3 mb-6 border-b border-gray-50 pb-4">
            <div class="p-3 bg-[#e8f5ed] text-[#0a5c36] rounded-2xl text-xl">📝</div>
            <div>
                <h2 class="text-xl font-black text-gray-800">التوصيات النهائية والملاحظات الإدارية</h2>
                <p class="text-xs text-gray-400 mt-1">الاعتماد المالي وقرار الإدارة النهائي لتسجيل الطالب</p>
            </div>
        </div>

        <div class="bg-[#e8f5ed]/50 rounded-2xl p-4 text-center border border-[#d4c98a] my-4">
            <div class="font-serif font-bold text-[#0a5c36] text-lg">« وَلَقَدْ يَسَّرْنَا الْقُرْآنَ لِلذِّكْرِ فَهَلْ مِن مُّدَّكِرٍ »</div>
            <div class="text-[10px] text-gray-400 mt-1">سورة القمر - آية ١٧</div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
            <div class="space-y-2">
                <label class="block text-sm font-bold text-gray-700">رسوم حجز المقعد</label>
                <input type="text" name="subscription_fees" data-field="subscription_fees"
                    value="{{ old('subscription_fees', $student->subscription_fees ?? '') }}"
                    placeholder="مثال: 150"
                    class="w-full p-3 bg-gray-50 border border-gray-200 rounded-2xl text-sm">
                <span data-error-for="subscription_fees" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                @error('subscription_fees')
                <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>
            <div class="space-y-2">
                <label class="block text-xs font-black text-gray-600">الأدوات والكتب المستلمة</label>
                <select name="received_tools" data-field="received_tools"
                    class="w-full p-3 bg-white border border-gray-200 rounded-2xl text-sm font-medium appearance-none">
                    <option value="" @selected(old('received_tools', $student->received_tools ?? '') == '')>-- اختر نوع العهدة --</option>
                    @foreach(['لم يأخذ شيء' => 'لم يأخذ شيء', 'المصحف فقط' => 'المصحف فقط', 'المتابعة فقط' => 'دفتر المتابعة فقط', 'المصحف والمتابعة' => 'المصحف ودفتر المتابعة معًا'] as $val => $lbl)
                    <option value="{{ $val }}" @selected(old('received_tools', $student->received_tools ?? '') == $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
                <span data-error-for="received_tools" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                @error('received_tools')
                <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @can('edit students')
            <div class="space-y-2">
                <label class="block text-sm font-bold text-gray-700">حالة الطالب</label>
                <select name="status" data-field="status" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-2xl text-sm">
                    @foreach(['مقيد', 'متوقف', 'مسافر'] as $statusOption)
                    <option value="{{ $statusOption }}" @selected(old('status', $student->status ?? 'مقيد') == $statusOption)>{{ $statusOption }}</option>
                    @endforeach
                </select>
                <span data-error-for="status" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                @error('status')
                <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>
            @else
            <input type="hidden" name="status" value="{{ old('status', $student->status ?? 'مقيد') }}">
            @endcan

            @can('manage student status')
            <div class="space-y-2">
                <label class="block text-sm font-bold text-gray-700">قرار الإدارة</label>
                <select name="decision" data-field="decision" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-2xl text-sm">
                    @foreach(['تحت الاختبار', 'مقبول', 'مرفوض'] as $decisionOption)
                    <option value="{{ $decisionOption }}" @selected(old('decision', $student->decision ?? 'تحت الاختبار') == $decisionOption)>{{ $decisionOption }}</option>
                    @endforeach
                </select>
                <span data-error-for="decision" class="hidden text-red-500 text-xs font-medium">هذا الحقل مطلوب</span>
                @error('decision')
                <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
                @enderror
            </div>
            @else
            <div class="space-y-2">
                <label class="block text-sm font-bold text-gray-700">قرار الإدارة</label>
                @php $decision = old('decision', $student->decision ?? 'تحت الاختبار'); @endphp
                <div class="w-full p-3 bg-gray-100 border border-gray-200 rounded-2xl text-sm text-gray-600 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full inline-block
                            {{ $decision === 'مقبول' ? 'bg-emerald-500' : ($decision === 'مرفوض' ? 'bg-red-500' : 'bg-amber-400') }}">
                    </span>
                    {{ $decision }}
                    <span class="text-xs text-gray-400 mr-auto">(صلاحيات الإدارة فقط)</span>
                </div>
                <input type="hidden" name="decision" value="{{ $decision }}">
            </div>
            @endcan
        </div>

        <div class="space-y-2">
            <div class="flex justify-between items-center">
                <label class="block text-sm font-bold text-gray-700">ملاحظات الشيخ المختبر / المشرف الفنية</label>
            </div>
            <textarea name="notes" data-field="notes"
                placeholder="اكتب التوصيات الخاصة بمخارج الحروف والتجويد..."
                class="w-full p-3 bg-gray-50 border border-gray-200 rounded-2xl text-sm min-h-25">{{ old('notes', $student->notes ?? '') }}</textarea>
            @error('notes')
            <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="flex justify-end items-center pt-6 border-t border-gray-100 mt-8">
        <button type="submit"
            class="flex items-center gap-2 px-8 py-3 bg-[#0a5c36] hover:bg-[#084d2d] text-white font-black rounded-2xl shadow-md transition-all text-sm">
            حفظ البيانات وإرسال النموذج ✓
        </button>
    </div>

    {{-- ============================================================
        JavaScript
    ============================================================ --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // ── 1) data-show-when ──────────────────────────────────────
            function initShowWhen(input) {
                const condition = input.getAttribute('data-show-when');
                if (!condition) return;

                const eqIndex = condition.indexOf('=');
                const fieldName = condition.substring(0, eqIndex);
                const expected = condition.substring(eqIndex + 1);

                function getVal() {
                    const checked = document.querySelector(`[name="${fieldName}"]:checked`);
                    if (checked) return checked.value;
                    const sel = document.querySelector(`select[name="${fieldName}"]`);
                    if (sel) return sel.value;
                    return '';
                }

                function toggle() {
                    input.style.display = getVal() === expected ? 'block' : 'none';
                }

                document.querySelectorAll(`[name="${fieldName}"]`).forEach(el => {
                    el.addEventListener('change', toggle);
                });

                toggle();
            }

            document.querySelectorAll('[data-show-when]').forEach(initShowWhen);

            // ── 3) فلترة الحلقات حسب النظام + عرض بطاقة معلومات الجماعي ─
            const circleSelect = document.getElementById('circleSelect');
            const groupPlanInfo = document.getElementById('groupPlanInfo');
            const groupPlanEmpty = document.getElementById('groupPlanEmpty');
            const groupPlanSurah = document.getElementById('groupPlanSurah');
            const groupPlanNew = document.getElementById('groupPlanNew');
            const groupPlanRevision = document.getElementById('groupPlanRevision');
            const groupPlanOld = document.getElementById('groupPlanOld');

            // ✅ فلاج لمنع مسح قيمة الحلقة المحفوظة عند أول تحميل لصفحة التعديل
            let isFirstCircleFilterRun = true;

            function filterCirclesBySystem() {
                if (!circleSelect) return;
                const selectedSystem = document.querySelector('input[name="study_system"]:checked')?.value;
                if (!selectedSystem) return;

                let currentValueStillValid = false;
                Array.from(circleSelect.options).forEach(opt => {
                    if (!opt.value) return;
                    const matches = opt.getAttribute('data-type') === selectedSystem;
                    opt.hidden = !matches;
                    if (matches && opt.value === circleSelect.value) currentValueStillValid = true;
                });

                // ✅ في أول تشغيل (تحميل الصفحة)، لا نمسح القيمة القادمة من السيرفر
                // حتى لو data-type مش متطابق، عشان نضمن ظهور بيانات التعديل
                // المسح يحصل فقط لما المستخدم يغيّر نظام الدراسة يدويًا بعد كده
                if (!currentValueStillValid && !isFirstCircleFilterRun) {
                    circleSelect.value = '';
                    hideGroupPlanInfo();
                }
                isFirstCircleFilterRun = false;
            }

            function hideGroupPlanInfo() {
                groupPlanInfo?.classList.add('hidden');
                groupPlanEmpty?.classList.add('hidden');
            }

            async function showGroupPlanInfo() {
                if (!circleSelect || !circleSelect.value) {
                    hideGroupPlanInfo();
                    return;
                }

                const opt = circleSelect.options[circleSelect.selectedIndex];
                const circleType = opt?.getAttribute('data-type');

                if (circleType !== 'group') {
                    hideGroupPlanInfo();
                    return;
                }

                try {
                    const res = await fetch(`/circles/${circleSelect.value}/group-plan`, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    if (!res.ok) return;

                    const data = await res.json();

                    if (!data.found) {
                        groupPlanInfo?.classList.add('hidden');
                        groupPlanEmpty?.classList.remove('hidden');
                        return;
                    }

                    if (groupPlanSurah) groupPlanSurah.textContent = data.current_surah_name ?? '—';
                    if (groupPlanNew) groupPlanNew.textContent = data.new_memorization_plan || '—';
                    if (groupPlanRevision) groupPlanRevision.textContent = data.revision_plan || '—';
                    if (groupPlanOld) groupPlanOld.textContent = data.old_memorization_plan || '—';

                    groupPlanEmpty?.classList.add('hidden');
                    groupPlanInfo?.classList.remove('hidden');
                } catch (e) {
                    console.error('Group plan fetch error:', e);
                }
            }

            document.querySelectorAll('input[name="study_system"]').forEach(radio => {
                radio.addEventListener('change', filterCirclesBySystem);
            });

            if (circleSelect) {
                circleSelect.addEventListener('change', showGroupPlanInfo);
            }

            filterCirclesBySystem();
            showGroupPlanInfo(); // في حالة edit لو الحلقة محددة مسبقًا

            // ── 4) guardian_id قبل الإرسال ────────────────────────────
            const studentForm = document.querySelector('form');
            if (studentForm) {
                studentForm.addEventListener('submit', function() {
                    const guardianInput = document.getElementById('guardianIdInput');
                    if (guardianInput && !guardianInput.value) {
                        guardianInput.value = 'none';
                    }
                });
            }

            // ── 5) استعادة حالة guardian بعد validation error ─────────
            const guardianIdVal = document.getElementById('guardianIdInput')?.value;
            if (guardianIdVal === 'new') {
                document.getElementById('newGuardianFields').style.display = 'grid';
            }

            // ── 6) ربط checkGuardianExists بحقل الواتساب ──────────────
            const whatsappInput = document.getElementById('whatsappInput');
            if (whatsappInput) {
                let whatsappTimer;
                whatsappInput.addEventListener('input', () => {
                    clearTimeout(whatsappTimer);
                    whatsappTimer = setTimeout(checkGuardianExists, 600);
                });
            }

            // ── 7) ربط قرار الإدارة "مرفوض" بحالة الطالب "متوقف" تلقائيًا ──────
            const decisionSelect = document.querySelector('select[name="decision"]');
            const statusSelect = document.querySelector('select[name="status"]');

            if (decisionSelect && statusSelect) {
                decisionSelect.addEventListener('change', function() {
                    if (this.value === 'مرفوض') {
                        statusSelect.value = 'متوقف';
                    }
                });
            }
        });

        // ── Alpine: guardianSearch ─────────────────────────────────────
        function guardianSearch() {
            return {
                query: '{{ addslashes($guardianQueryName) }}',
                results: [],
                selected: @json($guardianData ?? null),
                searching: false,
                noResults: false,

                async search() {
                    if (this.query.length < 2) {
                        this.results = [];
                        this.noResults = false;
                        return;
                    }

                    this.searching = true;
                    this.noResults = false;

                    try {
                        // ✅ URL محدث
                        const res = await fetch(
                            `/guardians/search?q=${encodeURIComponent(this.query)}`, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            }
                        );

                        if (!res.ok) {
                            console.error('Guardian search failed:', res.status);
                            return;
                        }

                        this.results = await res.json();
                        this.noResults = this.results.length === 0;

                        if (this.noResults) {
                            document.getElementById('guardianIdInput').value = 'new';
                            document.getElementById('newGuardianFields').style.display = 'grid';
                        } else {
                            document.getElementById('newGuardianFields').style.display = 'none';
                        }
                    } catch (e) {
                        console.error('Guardian search error:', e);
                    } finally {
                        this.searching = false;
                    }
                },

                select(guardian) {
                    this.selected = guardian;
                    this.query = guardian.name;
                    this.results = [];
                    this.noResults = false;
                    document.getElementById('guardianIdInput').value = guardian.id;
                    document.getElementById('newGuardianFields').style.display = 'none';
                    document.getElementById('guardianExistsAlert')?.classList.add('hidden');
                },

                clear() {
                    this.selected = null;
                    this.query = '';
                    this.results = [];
                    this.noResults = false;
                    document.getElementById('guardianIdInput').value = '';
                    document.getElementById('newGuardianFields').style.display = 'none';
                    document.getElementById('guardianExistsAlert')?.classList.add('hidden');
                },

                createNew() {
                    this.selected = null;
                    document.getElementById('guardianIdInput').value = 'new';
                    document.getElementById('newGuardianFields').style.display = 'grid';
                },

                skipGuardian() {
                    this.selected = {
                        id: null,
                        name: 'بدون ولي أمر',
                    };
                    this.query = '';
                    this.results = [];
                    this.noResults = false;
                    document.getElementById('guardianIdInput').value = 'none';
                    document.getElementById('newGuardianFields').style.display = 'none';
                },
            };
        }

        // ── checkGuardianExists ────────────────────────────────────────
        let _existingGuardianFromCheck = null;

        async function checkGuardianExists() {
            const email = document.getElementById('parentEmailInput')?.value?.trim() ?? '';

            if (!email) return;

            const params = new URLSearchParams();
            if (email) params.set('email', email);

            try {
                const res = await fetch(`/guardians/check?${params}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!res.ok) return;

                const data = await res.json();
                const alertEl = document.getElementById('guardianExistsAlert');
                const emailResult = document.getElementById('emailCheckResult');

                if (data.exists) {
                    _existingGuardianFromCheck = data;
                    alertEl?.classList.remove('hidden');
                    if (emailResult) {
                        emailResult.className = 'text-xs font-medium mt-1 text-amber-600';
                        emailResult.textContent = `⚠️ حساب موجود: ${data.name} (#${data.id})`;
                        emailResult.classList.remove('hidden');
                    }
                } else {
                    _existingGuardianFromCheck = null;
                    alertEl?.classList.add('hidden');
                    if (emailResult) {
                        emailResult.className = 'text-xs font-medium mt-1 text-emerald-600';
                        emailResult.textContent = '✓ متاح — سيُنشأ حساب جديد';
                        emailResult.classList.remove('hidden');
                    }
                }
            } catch (e) {
                console.error('Guardian check error:', e);
            }
        }

        // ── useExistingGuardian ────────────────────────────────────────
        function useExistingGuardian() {
            if (!_existingGuardianFromCheck) return;

            document.getElementById('guardianIdInput').value = _existingGuardianFromCheck.id;
            document.getElementById('newGuardianFields').style.display = 'none';
            document.getElementById('guardianExistsAlert')?.classList.add('hidden');
            document.getElementById('emailCheckResult')?.classList.add('hidden');

            _existingGuardianFromCheck = null;
        }

        // ── تحقق سريع من رقم واتساب عن طريق فتح wa.me ──────────────────
        function checkWhatsappNumber(inputId) {
            const input = document.getElementById(inputId);
            if (!input) return;

            let number = input.value.replace(/[^0-9]/g, '');

            if (!number) {
                showError('يرجى إدخال رقم أولًا');
                return;
            }

            // ✅ تحويل الرقم المصري المحلي (01xxxxxxxxx) لصيغة دولية (2xxxxxxxxxx)
            if (number.startsWith('0')) {
                number = '2' + number;
            }

            window.open(`https://wa.me/${number}`, '_blank');
        }
    </script>