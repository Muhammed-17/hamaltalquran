@php
$isGroup = $surahTest->test_type === 'group';

$cardClass = 'bg-white rounded-2xl border border-gray-100 p-6 md:p-8';
$labelClass = 'text-xs font-semibold text-gray-400';
$valueClass = 'text-sm font-bold text-[#1e2942] mt-1';

@endphp

<x-layouts.markaz-layout>
    <div class="max-w-5xl mx-auto space-y-6 py-6">

        <!-- ═══════════════════════════════════════ -->
        <!-- رأس الصفحة -->
        <!-- ═══════════════════════════════════════ -->
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-xl font-bold text-[#1e2942]">
                    @if($isGroup)
                    نتائج اختبار حلقة: {{ $surahTest->circle?->name ?? '—' }}
                    @else
                    نتيجة اختبار الطالب: {{ $surahTest->results->first()?->student?->name ?? '—' }}
                    @endif
                </h1>
                <p class="text-sm text-gray-400 mt-1">
                    سورة {{ $surahTest->surah?->name_arabic ?? '—' }}
                    · {{ $surahTest->test_date?->format('Y-m-d') ?? '—' }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                @can('update surah tests')
                <a href="{{ route('surah-tests.edit', $surahTest) }}"
                    class="inline-flex items-center gap-2 rounded-xl bg-[#0a5c36] text-white text-sm font-bold px-4 py-2.5 hover:bg-[#0a5c36]/90 transition">
                    تعديل النتائج
                </a>
                @endcan
                <a href="{{ route('surah-tests.index.individual') }}"
                    class="inline-flex items-center gap-2 rounded-xl bg-gray-50 text-gray-600 text-sm font-bold px-4 py-2.5 hover:bg-gray-100 transition">
                    رجوع
                </a>
            </div>
        </div>

        <!-- ═══════════════════════════════════════ -->
        <!-- معلومات الاختبار الأساسية -->
        <!-- ═══════════════════════════════════════ -->
        <div class="{{ $cardClass }}">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div>
                    <p class="{{ $labelClass }}">نوع الاختبار</p>
                    <p class="{{ $valueClass }}">{{ $isGroup ? 'جماعي' : 'فردي' }}</p>
                </div>
                <div>
                    <p class="{{ $labelClass }}">المعلم</p>
                    <p class="{{ $valueClass }}">{{ $surahTest->teacher?->user?->name ?? $surahTest->teacher?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="{{ $labelClass }}">السورة</p>
                    <p class="{{ $valueClass }}">{{ $surahTest->surah?->name_arabic ?? '—' }}</p>
                </div>
                <div>
                    <p class="{{ $labelClass }}">التاريخ</p>
                    <p class="{{ $valueClass }}">{{ $surahTest->test_date?->format('Y-m-d') ?? '—' }}</p>
                </div>

                @if($isGroup)
                <div>
                    <p class="{{ $labelClass }}">الحلقة</p>
                    <p class="{{ $valueClass }}">{{ $surahTest->circle?->name ?? '—' }}</p>
                </div>
                @endif
            </div>

            @if($surahTest->notes)
            <div class="mt-6 pt-6 border-t border-gray-100">
                <p class="{{ $labelClass }}">ملاحظات عامة</p>
                <p class="text-sm text-gray-600 mt-1">{{ $surahTest->notes }}</p>
            </div>
            @endif
        </div>
ًًًًًًًًًًً
            @if($isGroup && !($focusedStudentId ?? null))
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="{{ $cardClass }} p-5 text-center">
                    <p class="text-2xl font-bold text-[#1e2942]">{{ $stats['students_count'] }}</p>
                    <p class="text-xs text-gray-400 mt-1">عدد الطلاب</p>
                </div>
                <div class="{{ $cardClass }} p-5 text-center">
                    <p class="text-2xl font-bold text-[#0a5c36]">{{ $stats['average_percentage'] }}%</p>
                    <p class="text-xs text-gray-400 mt-1">متوسط النسبة</p>
                </div>
                <div class="{{ $cardClass }} p-5 text-center">
                    <p class="text-2xl font-bold text-emerald-600">{{ $stats['highest_percentage'] }}%</p>
                    <p class="text-xs text-gray-400 mt-1">أعلى نسبة</p>
                </div>
                <div class="{{ $cardClass }} p-5 text-center">
                    <p class="text-2xl font-bold text-red-500">{{ $stats['lowest_percentage'] }}%</p>
                    <p class="text-xs text-gray-400 mt-1">أقل نسبة</p>
                </div>
            </div>
            @endif

            <!-- ═══════════════════════════════════════ -->
            <!-- نتائج الطلاب -->
            <!-- نفس البنية تشتغل مع نتيجة واحدة (فردي) أو أكثر (جماعي) -->
            <!-- ═══════════════════════════════════════ -->
            <div>
                @if($isGroup && !($focusedStudentId ?? null))
                <h3 class="text-base font-bold text-[#1e2942] mb-4">
                    نتائج الطلاب
                    <span class="text-sm font-normal text-gray-400">({{ $surahTest->results->count() }} طالب)</span>
                </h3>
                @endif

                @if($isGroup && ($focusedStudentId ?? null))
                <h3 class="text-base font-bold text-[#1e2942] mb-4">
                    نتيجة الطالب: {{ $surahTest->results->first()?->student?->name ?? '—' }}
                </h3>
                @endif

                <div class="space-y-4">
                    @forelse($surahTest->results as $result)
                    <div class="{{ $cardClass }} p-4 md:p-5 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">

                        <!-- بيانات الطالب -->
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 shrink-0">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.4c-3.3 0-9.8 1.6-9.8 4.9v2.5h19.6v-2.5c0-3.3-6.5-4.9-9.8-4.9z" />
                                </svg>
                            </div>
                            <div class="text-right flex-1">
                                <div class="font-bold text-gray-900">{{ $result->student?->name ?? '—' }}</div>
                                <div class="text-xs text-gray-400">رقم الطالب: #{{ $result->student?->id ?? '—' }}</div>
                            </div>
                        </div>

                        <!-- النتائج -->
                        <div class="flex flex-wrap items-center gap-3 justify-end">
                            <div class="text-center w-20">
                                <p class="text-sm font-bold text-gray-700">{{ $result->prompt_errors }}</p>
                                <p class="text-[11px] font-semibold text-gray-400 mt-1">أخطاء الفتح</p>
                            </div>
                            <div class="text-center w-20">
                                <p class="text-sm font-bold text-gray-700">{{ $result->tashkeel_errors }}</p>
                                <p class="text-[11px] font-semibold text-gray-400 mt-1">أخطاء التشكيل</p>
                            </div>
                            <div class="text-center w-20">
                                <p class="text-sm font-bold text-[#0a5c36]">{{ $result->percentage }}%</p>
                                <p class="text-[11px] font-semibold text-gray-400 mt-1">النسبة</p>
                            </div>
                            @if($result->level)
                            <span class="text-xs font-bold px-3 py-1.5 rounded-full {{ $result->level->colorClasses() }}">
                                {{ $result->level }}
                            </span>
                            @endif
                        </div>

                        @if($result->notes)
                        <div class="w-full sm:w-auto text-sm text-gray-500 sm:max-w-xs sm:text-right">
                            {{ $result->notes }}
                        </div>
                        @endif
                    </div>
                    @empty
                    <div class="{{ $cardClass }} p-10 text-center text-gray-400 text-sm">
                        لا توجد نتائج مسجلة لهذا الاختبار.
                    </div>
                    @endforelse
                </div>
            </div>

    </div>
</x-layouts.markaz-layout>