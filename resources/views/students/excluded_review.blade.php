<x-layouts.markaz-layout>
    <div class="max-w-6xl mx-auto space-y-6">

        {{-- ─── Header ─── --}}
        <div class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden shadow-xl">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 relative z-10">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 bg-amber-400/20 rounded-2xl flex items-center justify-center border border-amber-400/30 shrink-0">
                        <svg class="w-6 h-6 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-black">مراجعة الطلاب المستثناة من التحديث التلقائي</h1>
                        <p class="text-emerald-100/70 text-sm font-medium mt-1">
                            هؤلاء الطلاب لن يتم تحديث مرحلتهم/صفهم تلقائيًا — يحتاجون مراجعة يدوية دوريًا.
                        </p>
                    </div>
                </div>

                <span class="text-sm font-bold bg-white/10 text-amber-200 px-4 py-2 rounded-2xl border border-white/10 shrink-0">
                    {{ $excluded->count() }} طالب
                </span>
            </div>
            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
        </div>

        @if($excluded->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 p-10 text-center text-gray-400">
            لا يوجد طلاب مستثناة حاليًا ✓
        </div>
        @else
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-4 py-3 font-bold">الاسم</th>
                        <th class="px-4 py-3 font-bold">الكود</th>
                        <th class="px-4 py-3 font-bold">السن</th>
                        <th class="px-4 py-3 font-bold">المرحلة الحالية</th>
                        <th class="px-4 py-3 font-bold">الصف الحالي</th>
                        <th class="px-4 py-3 font-bold">سبب الاستثناء</th>
                        <th class="px-4 py-3 font-bold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($excluded as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-semibold text-gray-800">{{ $row->student->name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $row->student->student_code }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $row->student->date_of_birth->age }}</td>
                        <td class="px-4 py-3">{{ $row->student->educational_stage ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $row->student->school_grade ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs bg-amber-50 text-amber-700 px-2 py-1 rounded-lg border border-amber-100">
                                {{ $row->reason }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @can('edit student')
                            <a href="{{ route('students.edit', $row->student->id) }}"
                                class="text-[#0a5c36] font-semibold hover:underline">
                                مراجعة
                            </a>
                            @endcan
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
        @endif
    </div>
</x-layouts.markaz-layout>