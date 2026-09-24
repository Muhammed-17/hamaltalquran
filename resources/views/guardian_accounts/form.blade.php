@php
$isEdit = isset($guardian) && $guardian->exists;
$title = $isEdit ? 'تعديل حساب ولي الأمر' : 'إضافة ولي أمر جديد';
$action = $isEdit
? route('guardians.update', $guardian)
: route('guardians.store');
@endphp

<x-layouts.markaz-layout>
    @section('title', $title)

    <div class="max-w-2xl mx-auto space-y-6">

        {{-- ─── Header ─── --}}
        <div class="flex items-center gap-4">
            <a href="{{ route('guardians.index') }}"
                class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-xl transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-black text-gray-800">{{ $title }}</h1>
                <p class="text-xs text-gray-400 mt-0.5">
                    @if($isEdit)
                    تعديل بيانات ولي الأمر: <span class="font-bold text-gray-600">{{ $guardian->name }}</span>
                    @else
                    إنشاء حساب جديد لولي أمر في النظام
                    @endif
                </p>
            </div>
        </div>

        {{-- ─── النموذج ─── --}}
        <form method="POST" action="{{ $action }}" class="space-y-5">
            @csrf
            @if($isEdit) @method('PUT') @endif

            {{-- ══════════════════════════════════════
                بطاقة البيانات الأساسية
            ══════════════════════════════════════ --}}
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 space-y-8">
                <div class="flex items-center gap-3 pb-4 border-b border-gray-50">
                    <div class="p-2.5 bg-emerald-50 text-[#0a5c36] rounded-xl text-lg">👤</div>
                    <div>
                        <h2 class="font-black text-gray-800">البيانات الأساسية</h2>
                        <p class="text-xs text-gray-400">الاسم ومعلومات التواصل والفرع</p>
                    </div>
                </div>

                {{-- الاسم --}}
                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">
                        الاسم الكامل <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name"
                        value="{{ old('name', $guardian->name ?? '') }}"
                        placeholder="اسم ولي الأمر رباعياً"
                        class="w-full p-3 bg-white border rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all
                            {{ $errors->has('name') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">
                    @error('name')
                    <p class="text-red-500 text-xs font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                {{-- الفرع --}}
                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">الفرع</label>
                    <select name="center_id"
                        class="w-full p-3 bg-white border rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all appearance-none
                            {{ $errors->has('center_id') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">
                        <option value="">— بدون فرع محدد —</option>
                        @foreach($centers ?? [] as $center)
                        <option value="{{ $center->id }}"
                            @selected(old('center_id', $guardian->center_id ?? '') == $center->id)>
                            {{ $center->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('center_id')
                    <p class="text-red-500 text-xs font-semibold">{{ $message }}</p>
                    @enderror
                </div>

            </div>

            {{-- ══════════════════════════════════════
                بطاقة بيانات الحساب
            ══════════════════════════════════════ --}}
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 space-y-8">
                <div class="flex items-center gap-3 pb-4 border-b border-gray-50">
                    <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl text-lg">🔐</div>
                    <div>
                        <h2 class="font-black text-gray-800">بيانات الحساب</h2>
                        <p class="text-xs text-gray-400">البريد الإلكتروني وكلمة المرور لتسجيل الدخول</p>
                    </div>
                </div>

                {{-- الإيميل --}}
                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">
                        البريد الإلكتروني
                    </label>
                    <input type="email" name="email"
                        value="{{ old('email', $guardian->email ?? '') }}"
                        placeholder="name@markaz.com"

                        class="w-full p-3 bg-white border rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all {{ $errors->has('email') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">
                    @error('email')
                    <p class="text-red-500 text-xs font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                {{-- كلمة المرور --}}
                <div class="space-y-2" x-data="{ showPass: false }">
                    <label class="block text-sm font-bold text-gray-700">
                        كلمة المرور
                        <span class="text-gray-400 font-normal text-xs">
                            @if($isEdit)
                            (اتركها فارغة للإبقاء على الحالية)
                            @endif
                        </span>
                    </label>
                    <div class="relative">
                        <input :type="showPass ? 'text' : 'password'" name="password"
                            placeholder="{{ $isEdit ? 'أدخل كلمة مرور جديدة...' : 'الرجاء إدخال كلمة مرور حتى تتمكن من إنشاء الحساب' }}"
                            class="w-full p-3 pl-11 bg-white border rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all
                                {{ $errors->has('password') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">
                        <button type="button" @click="showPass = !showPass"
                            class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                            <svg x-show="!showPass" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg x-show="showPass" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                    @error('password')
                    <p class="text-red-500 text-xs font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                {{-- تأكيد كلمة المرور --}}
                <div class="space-y-2" x-data="{ showConfirm: false }">
                    <label class="block text-sm font-bold text-gray-700">تأكيد كلمة المرور</label>
                    <div class="relative">
                        <input :type="showConfirm ? 'text' : 'password'" name="password_confirmation"
                            placeholder="أعد إدخال كلمة المرور..."
                            class="w-full p-3 pl-11 bg-white border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all">
                        <button type="button" @click="showConfirm = !showConfirm"
                            class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                            <svg x-show="!showConfirm" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg x-show="showConfirm" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                </div>

            </div>

            {{-- ══════════════════════════════════════
                بطاقة الطلاب المرتبطين (edit فقط)
            ══════════════════════════════════════ --}}
            @if($isEdit && isset($guardian->students) && $guardian->students->isNotEmpty())
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 space-y-4">
                <div class="flex items-center gap-3 pb-4 border-b border-gray-50">
                    <div class="p-2.5 bg-purple-50 text-purple-600 rounded-xl text-lg">🎓</div>
                    <div>
                        <h2 class="font-black text-gray-800">الطلاب المرتبطين</h2>
                        <p class="text-xs text-gray-400">
                            {{ $guardian->students->count() }}
                            {{ $guardian->students->count() === 1 ? 'طالب مرتبط' : 'طلاب مرتبطون' }}
                            بهذا الحساب
                        </p>
                    </div>
                </div>
                <div class="space-y-2">
                    @foreach($guardian->students as $student)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-2xl">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-mono bg-white border border-gray-200 text-gray-400 px-2 py-0.5 rounded-lg">
                                {{ $student->student_code ?? '#' . $student->id }}
                            </span>
                            <span class="text-sm font-bold text-gray-700">{{ $student->name }}</span>
                        </div>
                        <span class="text-xs px-2.5 py-1 rounded-lg font-bold
                            {{ $student->status === 'مقيد'
                                ? 'bg-emerald-100 text-emerald-700'
                                : ($student->status === 'مسافر'
                                    ? 'bg-blue-100 text-blue-700'
                                    : 'bg-orange-100 text-orange-600') }}">
                            {{ $student->status }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- ─── أزرار الحفظ ─── --}}
            <div class="flex items-center justify-between gap-3 pt-2">
                <a href="{{ route('guardians.index') }}"
                    class="px-6 py-3 text-sm font-bold text-gray-500 hover:text-gray-700 border border-gray-200 hover:border-gray-300 bg-white rounded-2xl transition-colors">
                    إلغاء
                </a>
                <button type="submit"
                    class="px-8 py-3 bg-[#0a5c36] hover:bg-[#084a2b] text-white text-sm font-black rounded-2xl transition-colors shadow-sm">
                    {{ $isEdit ? '💾 حفظ التعديلات' : '✓ إنشاء الحساب' }}
                </button>
            </div>

        </form>
    </div>
</x-layouts.markaz-layout>