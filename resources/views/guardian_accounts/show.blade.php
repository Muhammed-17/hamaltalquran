<x-layouts.markaz-layout>
    @section('title', 'بيانات ولي الأمر: ' . $guardian->name)

    <div class="max-w-3xl mx-auto space-y-6">

        {{-- ─── Header ─── --}}
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('guardians.index') }}"
                    class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-xl transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-black text-gray-800">{{ $guardian->name }}</h1>
                    <p class="text-xs text-gray-400 mt-0.5">بيانات حساب ولي الأمر</p>
                </div>
            </div>

            {{-- أزرار الإجراءات --}}
            <div class="flex items-center gap-2">
                {{-- تعديل --}}
                <a href="{{ route('guardians.edit', $guardian) }}"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 text-sm font-bold rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    تعديل
                </a>

                {{-- تفعيل / تعطيل --}}
                <form method="POST" action="{{ route('guardians.toggleStatus', $guardian) }}">
                    @csrf @method('PATCH')
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-bold rounded-xl transition-colors
                               {{ $guardian->status === 'active'
                                   ? 'bg-orange-50 hover:bg-orange-100 text-orange-600'
                                   : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700' }}">
                        @if($guardian->status === 'active')
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                        تعطيل
                        @else
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        تفعيل
                        @endif
                    </button>
                </form>

                {{-- حذف --}}
                @if($guardian->students->isEmpty())
                <form method="POST" action="{{ route('guardians.destroy', $guardian) }}"
                    onsubmit="return confirm('هل أنت متأكد من حذف هذا الحساب نهائياً؟')">
                    @csrf @method('DELETE')
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 text-sm font-bold rounded-xl transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        حذف
                    </button>
                </form>
                @endif
            </div>
        </div>

        {{-- ─── بطاقة البيانات الأساسية ─── --}}
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 space-y-5">
            <div class="flex items-center gap-3 pb-4 border-b border-gray-50">
                <div class="p-2.5 bg-emerald-50 text-[#0a5c36] rounded-xl text-lg">👤</div>
                <div>
                    <h2 class="font-black text-gray-800">البيانات الأساسية</h2>
                    <p class="text-xs text-gray-400">معلومات ولي الأمر الشخصية</p>
                </div>
                {{-- badge الحالة --}}
                <div class="mr-auto">
                    @if($guardian->status === 'active')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-100 text-xs font-bold rounded-xl">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>
                        نشط
                    </span>
                    @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-50 text-orange-600 border border-orange-100 text-xs font-bold rounded-xl">
                        <span class="w-1.5 h-1.5 rounded-full bg-orange-400 inline-block"></span>
                        غير نشط
                    </span>
                    @endif
                </div>
            </div>
            <div class="flex items-center justify-between p-3.5 bg-gray-50 rounded-2xl">
                <p class="text-xs text-gray-400">
                    آخر تسجيل دخول:
                    <span class="font-bold text-gray-600">
                        {{ $guardian->last_login_at ? $guardian->last_login_at->locale('ar')->diffForHumans() : 'لم يسجل الدخول مسبقًا' }}
                    </span>
                </p>
                <div class="flex items-center gap-2">
                    @if($guardian->last_seen_at && $guardian->last_seen_at->gt(now()->subMinutes(3)))
                    <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                    <span class="text-sm font-bold text-emerald-600">متصل الآن</span>
                    @else
                    <span class="w-2 h-2 rounded-full bg-gray-300 inline-block"></span>
                    <span class="text-sm font-bold text-gray-400">غير متصل</span>
                    @endif
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                {{-- الاسم --}}
                <div class="space-y-1">
                    <p class="text-xs font-bold text-gray-400">الاسم الكامل</p>
                    <p class="text-sm font-bold text-gray-800">{{ $guardian->name }}</p>
                </div>

                {{-- الإيميل --}}
                <div class="space-y-1">
                    <p class="text-xs font-bold text-gray-400">البريد الإلكتروني</p>
                    <p class="text-sm font-medium text-gray-700 font-mono">
                        {{ $guardian->email ?? '—' }}
                    </p>
                </div>

                {{-- الفرع --}}
                <div class="space-y-1">
                    <p class="text-xs font-bold text-gray-400">الفرع</p>
                    <p class="text-sm font-medium text-gray-700">
                        {{ $guardian->center?->name ?? '—' }}
                    </p>
                </div>

                {{-- تاريخ التسجيل --}}
                <div class="space-y-1">
                    <p class="text-xs font-bold text-gray-400">تاريخ إنشاء الحساب</p>
                    <p class="text-sm font-medium text-gray-700">
                        {{ $guardian->created_at->format('Y/m/d') }}
                    </p>
                </div>

                {{-- آخر تحديث --}}
                <div class="space-y-1">
                    <p class="text-xs font-bold text-gray-400">آخر تحديث</p>
                    <p class="text-sm font-medium text-gray-700">
                        {{ $guardian->updated_at->diffForHumans() }}
                    </p>
                </div>
            </div>
        </div>

        {{-- ─── بطاقة الطلاب المرتبطين ─── --}}
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 space-y-4">
            <div class="flex items-center gap-3 pb-4 border-b border-gray-50">
                <div class="p-2.5 bg-purple-50 text-purple-600 rounded-xl text-lg">🎓</div>
                <div>
                    <h2 class="font-black text-gray-800">الطلاب المرتبطين</h2>
                    <p class="text-xs text-gray-400">
                        @if($guardian->students->isEmpty())
                        لا يوجد طلاب مرتبطون بهذا الحساب
                        @elseif($guardian->students->count() === 1)
                        طالب واحد مرتبط بهذا الحساب
                        @elseif($guardian->students->count() === 2)
                        طالبان مرتبطان بهذا الحساب
                        @else
                        {{ $guardian->students->count() }} طلاب مرتبطون بهذا الحساب
                        @endif
                    </p>
                </div>
            </div>

            @if($guardian->students->isEmpty())
            <div class="py-8 text-center">
                <div class="text-4xl mb-2">📭</div>
                <p class="text-gray-400 text-sm font-medium">لا يوجد طلاب مرتبطون بهذا الحساب</p>
            </div>
            @else
            <div class="space-y-2">
                @foreach($guardian->students as $student)
                <div class="flex items-center justify-between p-3.5 bg-gray-50 hover:bg-gray-100 rounded-2xl transition-colors">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-mono bg-white border border-gray-200 text-gray-400 px-2 py-0.5 rounded-lg">
                            {{ $student->student_code ?? '#' . $student->id }}
                        </span>
                        <span class="text-sm font-bold text-gray-800">{{ $student->name }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs px-2.5 py-1 rounded-lg font-bold
                                {{ $student->status === \App\Enums\StudentStatus::Enrolled
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : ($student->status === \App\Enums\StudentStatus::Traveling
                                        ? 'bg-blue-100 text-blue-700'
                                        : 'bg-orange-100 text-orange-600') }}">
                            {{ $student->status }}
                        </span>
                        @can('view students')
                        <a href="{{ route('students.show', $student) }}"
                            class="p-1.5 text-gray-400 hover:text-[#0a5c36] hover:bg-emerald-50 rounded-lg transition-colors"
                            title="عرض الطالب">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                        @endcan
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

    </div>
</x-layouts.markaz-layout>