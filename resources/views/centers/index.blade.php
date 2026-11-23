<x-layouts.markaz-layout>
    <div class="space-y-6">
        <!-- Header Card -->
        <div
            class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6 mb-8">
            <div class="order-1 md:order-1 text-right w-full md:w-auto z-10">
                <h1 class="text-3xl font-black mb-2">إدارة الفروع (المراكز)</h1>
                <p class="text-emerald-100/80 text-sm font-medium">إضافة، تعديل أو حذف الفروع التابعة للمركز الرئيسي</p>
            </div>
            <!-- Decorative Element -->
            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
        </div>

        <!-- Add/Update Form -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <h2 id="formTitle" class="text-lg font-bold text-gray-800 mb-4">إضافة فرع جديد</h2>
            <form id="centerForm" action="{{ route('centers.store') }}" method="POST"
                class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                @csrf
                <input type="hidden" name="id" id="centerId">

                <div class="md:col-span-2">
                    <x-custom-input name="name" id="centerName" type="text" value="{{ old('name') }}" placeholder="مثال: الفرع الرئيسي"
                        label="اسم الفرع *" />
                </div>

                <div class="md:col-span-1">
                    <x-custom-input name="established_at" id="centerEstablishedAt" type="date" value="{{ old('established_at') }}"
                        label="تاريخ الإنشاء الفعلي" />
                </div>

                <div class="flex gap-2 w-full">
                    <button type="submit"
                        class="bg-[#10b981] hover:bg-[#059669] text-white px-6 py-2 rounded-lg font-bold transition h-10.5 flex-1">
                        حفظ
                    </button>
                    <button type="button" onclick="resetForm()" id="cancelBtn"
                        class="hidden bg-gray-100 hover:bg-gray-200 text-gray-600 px-4 py-2 rounded-lg font-bold transition h-10.5">
                        إلغاء
                    </button>
                </div>
            </form>
        </div>

        <!-- Centers Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-right">
                <thead class="bg-gray-50 text-gray-500 text-sm">
                    <tr>
                        <th class="py-4 px-6 font-medium">اسم الفرع</th>
                        <th class="py-4 px-6 font-medium">تاريخ الإنشاء</th>
                        <th class="py-4 px-6 font-medium">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($centers as $center)
                    <tr class="hover:bg-gray-50/50">
                        <td class="py-4 px-6 text-gray-800 font-medium">{{ $center->name }}</td>
                        <td class="py-4 px-6 text-gray-400 text-sm">{{ $center->established_at?->format('Y/m/d') ?? '—' }}</td>
                        <td class="py-4 px-6 flex items-center gap-3">
                            <a href="{{ route('centers.show', $center) }}"
                                class="text-emerald-500 hover:text-emerald-700 transition" title="عرض">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </a>
                            <button
                                onclick="editCenter('{{ $center->id }}', '{{ $center->name }}', '{{ $center->established_at?->format('Y-m-d') }}')"
                                class="text-blue-400 hover:text-blue-600 transition" title="تعديل">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <form action="{{ route('centers.destroy', $center) }}" method="POST"
                                onsubmit="confirmDelete(event, { name: '{{ e($center->name) }}', type: 'الفرع' })">
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
                        <td colspan="3" class="py-8 text-center text-gray-400">لا توجد فروع مسجلة حالياً</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <script>
        function editCenter(id, name, establishedAt) {
            document.getElementById('centerId').value = id;
            document.querySelector('[name="name"]').value = name;
            document.getElementById('centerEstablishedAt').value = establishedAt ?? '';
            document.getElementById('formTitle').innerText = 'تعديل بيانات الفرع';
            document.getElementById('cancelBtn').classList.remove('hidden');
            document.getElementById('centerForm').scrollIntoView({
                behavior: 'smooth'
            });
        }

        function resetForm() {
            document.getElementById('centerId').value = '';
            document.querySelector('[name="name"]').value = '';
            document.getElementById('centerEstablishedAt').value = '';
            document.getElementById('formTitle').innerText = 'إضافة فرع جديد';
            document.getElementById('cancelBtn').classList.add('hidden');
        }
    </script>
</x-layouts.markaz-layout>