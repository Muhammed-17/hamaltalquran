<x-layouts.markaz-layout>
    <div class="space-y-6">
        <!-- Header Card -->
        <div
            class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6 mb-8">
            <div class="order-1 md:order-1 text-right w-full md:w-auto z-10">
                <h1 class="text-3xl font-black mb-2">إعدادات أسعار الاشتراكات</h1>
                <p class="text-emerald-100/80 text-sm font-medium">تحديد رسوم الاشتراك لكل مرحلة وحلقة</p>
            </div>

            <!-- Decorative Element -->
            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
        </div>

        <!-- Add/Update Form -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <h2 class="text-lg font-bold text-gray-800 mb-4">إضافة / تعديل سعر</h2>
            <form id="priceForm" action="{{ route('subscription-prices.store') }}" method="POST"
                class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                @csrf

                <!-- Education Level -->
                <x-custom-select name="education_stage" label="المرحلة الدراسية">
                    <option value="تمهيدي" {{ old('education_stage') == 'تمهيدي' ? 'selected' : '' }}>تمهيدي</option>
                    <option value="حضانة" {{ old('education_stage') == 'حضانة' ? 'selected' : '' }}>حضانة</option>
                    <option value="ابتدائي" {{ old('education_stage') == 'ابتدائي' ? 'selected' : '' }}>ابتدائي</option>
                    <option value="اعدادي" {{ old('education_stage') == 'اعدادي' ? 'selected' : '' }}>اعدادي</option>
                    <option value="ثانوي" {{ old('education_stage') == 'ثانوي' ? 'selected' : '' }}>ثانوي</option>
                    <option value="جامعي" {{ old('education_stage') == 'جامعي' ? 'selected' : '' }}>جامعي</option>
                    <option value="خريج" {{ old('education_stage') == 'خريج' ? 'selected' : '' }}>خريج</option>
                </x-custom-select>
                <!-- School Grade -->
                <!-- <x-custom-select name="school_grade" label="الصف الدراسي">
                    <option value="" {{ old('school_grade') == '' ? 'selected' : '' }}>-- اختر الصف --</option>
                    <option value="لا يوجد" {{ old('school_grade') == 'لا يوجد' ? 'selected' : '' }}>لا يوجد</option>
                    <option value="الأول" {{ old('school_grade') == 'الأول' ? 'selected' : '' }}>الأول</option>
                    <option value="الثاني" {{ old('school_grade') == 'الثاني' ? 'selected' : '' }}>الثاني</option>
                    <option value="الثالث" {{ old('school_grade') == 'الثالث' ? 'selected' : '' }}>الثالث</option>
                    <option value="الرابع" {{ old('school_grade') == 'الرابع' ? 'selected' : '' }}>الرابع</option>
                    <option value="الخامس" {{ old('school_grade') == 'الخامس' ? 'selected' : '' }}>الخامس</option>
                    <option value="السادس" {{ old('school_grade') == 'السادس' ? 'selected' : '' }}>السادس</option>
                    <option value="دراسات عليا" {{ old('school_grade') == 'دراسات عليا' ? 'selected' : '' }}>دراسات عليا</option>
                </x-custom-select> -->

                <x-custom-select name="circle_level" label="مستوى الحلقة">
                    <option value="build" {{ old('circle_level') == 'build' ? 'selected' : '' }}>بناء</option>
                    <option value="mastery" {{ old('circle_level') == 'mastery' ? 'selected' : '' }}>إتقان</option>
                    <option value="creativity" {{ old('circle_level') == 'creativity' ? 'selected' : '' }}>إبداع</option>
                </x-custom-select>

                <x-custom-input name="amount" type="number" value="{{ old('amount') }}" placeholder="0.00"
                    label="قيمة الاشتراك (ج.م)" />

                <div class="flex gap-2">
                    <button type="submit"
                        class="bg-[#10b981] hover:bg-[#059669] text-white px-6 py-2 rounded-lg font-bold transition h-10.5 flex-1">
                        حفظ
                    </button>
                </div>
            </form>
        </div>

        <!-- Prices Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-right">
                <thead class="bg-gray-50 text-gray-500 text-sm">
                    <tr>
                        <th class="py-4 px-6 font-medium">المرحلة الدراسية</th>
                        <th class="py-4 px-6 font-medium">الصف الدراسي</th>
                        <th class="py-4 px-6 font-medium">مستوى الحلقة</th>
                        <th class="py-4 px-6 font-medium">قيمة الاشتراك</th>
                        <th class="py-4 px-6 font-medium">تاريخ التحديث</th>
                        <th class="py-4 px-6 font-medium">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($prices as $price)
                    <tr class="hover:bg-gray-50/50">
                        <td class="py-4 px-6 text-gray-800 font-medium">
                            @if ($price->education_stage == 'تمهيدي')
                            تمهيدي
                            @elseif ($price->education_stage == 'حضانة')
                            حضانة
                            @elseif ($price->education_stage == 'ابتدائي')
                            ابتدائي
                            @elseif ($price->education_stage == 'اعدادي')
                            اعدادي
                            @elseif ($price->education_stage == 'ثانوي')
                            ثانوي
                            @elseif ($price->education_stage == 'جامعي')
                            جامعي
                            @elseif ($price->education_stage == 'خريج')
                            خريج
                            @endif
                        </td>
                        <td class="py-4 px-6 text-gray-600">{{ $price->school_grade ?? '—' }}</td>
                        <td class="py-4 px-6 text-gray-600">
                            @if ($price->circle_level == 'build')
                            بناء
                            @elseif ($price->circle_level == 'mastery')
                            إتقان
                            @elseif ($price->circle_level == 'creativity')
                            إبداع
                            @endif
                        </td>
                        <td class="py-4 px-6 text-[#10b981] font-bold">{{ number_format($price->amount, 2) }} ج.م
                        </td>
                        <td class="py-4 px-6 text-gray-400 text-sm">{{ $price->updated_at->format('Y/m/d') }}</td>
                        <td class="py-4 px-6 flex items-center gap-3">
                            <button
                                onclick="editPrice('{{ $price->education_stage }}', '{{ $price->school_grade }}', '{{ $price->circle_level }}', '{{ $price->amount }}')"
                                class="text-blue-400 hover:text-blue-600 transition" title="تعديل">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <form action="{{ route('subscription-prices.destroy', $price) }}" method="POST"
                                onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-400 hover:text-red-600 transition"
                                    title="حذف">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-gray-400">لا توجد أسعار محددة حالياً</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <script>
        function editPrice(edu, grade, circle, amount) {
            document.querySelector('[name="education_stage"]').value = edu;
            // document.querySelector('[name="school_grade"]').value = grade;
            document.querySelector('[name="circle_level"]').value = circle;
            document.querySelector('[name="amount"]').value = amount;
            document.getElementById('priceForm').scrollIntoView({
                behavior: 'smooth'
            });
        }

        function resetForm() {
            document.getElementById('priceForm').reset();
        }
    </script>
</x-layouts.markaz-layout>