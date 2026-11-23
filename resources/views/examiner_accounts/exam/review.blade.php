<x-layouts.markaz-layout>
    <x-slot name="title">مراجعة الاختبار</x-slot>

    <nav class="text-xs text-gray-400 mb-4 flex items-center gap-1">
        <a href="{{ route('examiner.dashboard') }}" class="hover:text-[#0a5c36]">لوحة التحكم</a>
        <span>/</span>
        <span>مراجعة الاختبار</span>
    </nav>

    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 text-white shadow-xl mb-6">
        <h1 class="text-xl font-black mb-1">{{ $participant->participant_name }}</h1>
        <p class="text-emerald-100/80 text-sm">مراجعة الاختبار قبل اعتماد النتيجة</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 text-center">
            <p class="text-2xl font-black text-gray-800">{{ $totalQuestions }}</p>
            <p class="text-xs text-gray-500 mt-1">عدد الأسئلة</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 text-center">
            <p class="text-2xl font-black text-emerald-600">{{ $answeredCount }}</p>
            <p class="text-xs text-gray-500 mt-1">مُجاب عنها</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 text-center">
            <p class="text-2xl font-black text-red-500">{{ $unansweredCount }}</p>
            <p class="text-xs text-gray-500 mt-1">غير مُجاب</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs">
                    <tr>
                        <th class="px-6 py-3 text-right font-bold">السؤال</th>
                        <th class="px-6 py-3 text-right font-bold">أجاب</th>
                        <th class="px-6 py-3 text-right font-bold">تعديل</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($answers as $answer)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-6 py-4 font-medium text-gray-800">{{ $answer->competitionQuestion?->name }}</td>
                        <td class="px-6 py-4">
                            @if($answer->answered)
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">نعم</span>
                            @else
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-600">لا</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <a href="{{ route('examiner.exam.show', $participant) }}?question={{ $answer->competition_question_id }}"
                                class="text-xs font-bold text-[#0a5c36] hover:underline">تعديل</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <form method="POST" action="{{ route('examiner.exam.finalize', $participant) }}" id="finalize-form">
        @csrf
        <div class="flex flex-col md:flex-row gap-3">
            <button type="button" id="finalize-btn"
                class="flex-1 px-5 py-3 bg-[#0a5c36] hover:bg-[#0d7a48] text-white font-bold rounded-xl transition-all">
                اعتماد النتيجة
            </button>
            <a href="{{ route('examiner.participants.index', $participant->competition_level_id) }}"
                class="flex-1 text-center px-5 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition-all">
                الرجوع للمشاركين
            </a>
        </div>
    </form>

    <script>
        document.getElementById('finalize-btn').addEventListener('click', function () {
            Swal.fire({
                icon: 'warning',
                title: 'تأكيد اعتماد النتيجة',
                text: 'هل أنت متأكد من اعتماد النتيجة؟ لن تتمكن من التعديل بعدها.',
                showCancelButton: true,
                confirmButtonColor: '#0a5c36',
                cancelButtonColor: '#6b7280',
                cancelButtonText: 'إلغاء',
                confirmButtonText: 'نعم، اعتماد',
                customClass: {
                    popup: 'rounded-3xl font-bold',
                    confirmButton: 'rounded-xl px-6 py-2.5 text-sm',
                    cancelButton: 'rounded-xl px-6 py-2.5 text-sm',
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('finalize-form').submit();
                }
            });
        });
    </script>
</x-layouts.markaz-layout>