<x-layouts.markaz-layout>
    <div class="max-w-3xl mx-auto px-3 sm:px-0" dir="rtl">
        <a href="{{ route('badges.shortages.index') }}" class="inline-flex items-center gap-1.5 text-sm font-bold text-gray-500 hover:text-[#0a5c36] mb-4 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            رجوع للأوسمة الناقصة
        </a>

        <div class="mb-5">
            <h1 class="text-xl sm:text-2xl font-black">تسليم المستحق</h1>
            <p class="text-sm text-gray-500 mt-0.5">الطالب: <b class="text-gray-700">{{ $student->name }}</b> — الموزّع: <b class="text-gray-700">{{ $distributor->name }}</b>. الصفحة تُسلّم من متبقّيات قائمة فقط ولا تفتح استحقاقات جديدة، والكمية المقترحة هي القابلة للتسليم الآن (المتبقّي أو رصيد الموزّع، أيّهما أقل).</p>
        </div>

        <form method="POST" action="{{ route('badges.shortages.deliver-store') }}">
            @csrf
            <input type="hidden" name="teacher_id" value="{{ $distributor->id }}">
            <input type="hidden" name="student_id" value="{{ $student->id }}">

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto mb-6">
                <table class="w-full text-right">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">نوع الوسام</th>
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">المتبقّي</th>
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">رصيد الموزّع</th>
                            <th class="px-5 py-3 text-sm font-bold text-gray-600">يُسلَّم الآن</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-5 py-3 text-sm font-bold">{{ $row['type']->name }}</td>
                                <td class="px-5 py-3 text-sm font-black text-red-600">{{ $row['remaining'] }}</td>
                                <td class="px-5 py-3 text-sm">{{ $row['balance'] }}</td>
                                <td class="px-5 py-3">
                                    <input type="number" name="quantities[{{ $row['type']->id }}]" value="{{ old('quantities.' . $row['type']->id, $row['deliverable']) }}" min="0" max="{{ $row['remaining'] }}" inputmode="numeric"
                                        class="w-24 p-2 px-3 border border-gray-200 rounded-xl text-sm text-center focus:outline-none focus:ring-1 focus:ring-[#0a5c36]">
                                    @if ($row['deliverable'] === 0)
                                        <span class="block text-[11px] font-bold text-gray-400 mt-1">لا رصيد عند الموزّع لهذا النوع</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-2xl transition-all active:scale-95">تسليم</button>
                <a href="{{ route('badges.shortages.index') }}" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold rounded-2xl transition-all">إلغاء</a>
            </div>
        </form>
    </div>
</x-layouts.markaz-layout>
