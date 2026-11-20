<x-layouts.markaz-layout>
    @section('title', 'إدارة حسابات أولياء الأمور')

    <div class="space-y-6" x-data="{ processing: false }">

        {{-- ─── الهيدر الرئيسي ─── --}}
        <div class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6">
            <div class="text-right w-full md:w-auto z-10">
                <h1 class="text-3xl font-black mb-2">إدارة حسابات أولياء الأمور</h1>
                <p class="text-emerald-100/80 text-sm font-medium">
                    @if(request()->anyFilled(['q', 'status', 'center_id']))
                    {{ $guardians->total() }} نتيجة من {{ \App\Models\User::role('guardian')->count() }}
                    @else
                    {{ $guardians->total() }} ولي أمر مسجل في النظام
                    @endif
                </p>
            </div>

            {{-- ✅ زر الإضافة --}}
            <a href="{{ route('guardians.create') }}"
                class="z-10 shrink-0 inline-flex items-center gap-2 px-5 py-3 bg-white/10 hover:bg-white/20 border border-white/20 text-white text-sm font-bold rounded-2xl transition-all backdrop-blur-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                </svg>
                تسجيل ولي أمر جديد
            </a>

            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
        </div>

        {{-- ─── فلاتر التصفية السريعة (GET form حقيقي) ─── --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
            <form method="GET" action="{{ route('guardians.index') }}" class="flex flex-col lg:flex-row gap-4 items-end">

                {{-- مربع البحث --}}
                <div class="w-full lg:flex-1" dir="rtl">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">البحث المتقدم</label>
                    <input type="search" name="q" value="{{ request('q') }}"
                        placeholder="ابحث باسم ولي الأمر، البريد الإلكتروني..."
                        class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-1 focus:ring-[#0a5c36] focus:border-[#0a5c36] transition-all bg-white text-right"
                        dir="rtl">
                </div>

                {{-- فلترة الحالة --}}
                <div class="w-full lg:w-48">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">حسب الحالة</label>
                    <select name="status"
                        class="w-full p-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-1 focus:ring-[#0a5c36] focus:border-[#0a5c36] transition-all bg-white appearance-none">
                        <option value="">كل الحالات</option>
                        <option value="active" @selected(request('status')==='active' )>نشط</option>
                        <option value="inactive" @selected(request('status')==='inactive' )>متوقف</option>
                    </select>
                </div>

                {{-- فلترة الفرع --}}
                <div class="w-full lg:w-48">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5">حسب الفرع</label>
                    <select name="center_id"
                        class="w-full p-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-1 focus:ring-[#0a5c36] focus:border-[#0a5c36] transition-all bg-white appearance-none">
                        <option value="">كل الفروع</option>
                        @foreach($centers ?? [] as $center)
                        <option value="{{ $center->id }}" @selected((string) request('center_id')===(string) $center->id)>
                            {{ $center->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- زر البحث --}}
                <button type="submit"
                    class="w-full lg:w-auto px-5 py-2.5 bg-[#0a5c36] hover:bg-[#08492a] text-white font-bold rounded-xl text-sm transition-all text-center">
                    بحث
                </button>

                {{-- زر مسح الفلاتر --}}
                @if(request()->anyFilled(['q', 'status', 'center_id']))
                <a href="{{ route('guardians.index') }}"
                    class="w-full lg:w-auto px-5 py-2.5 bg-gray-50 hover:bg-gray-100 text-gray-500 hover:text-gray-700 font-bold border border-gray-200 rounded-xl text-sm transition-all text-center">
                    مسح الفلاتر
                </a>
                @endif
            </form>
        </div>

        {{-- ─── جدول العرض الرئيسي ─── --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-right min-w-[900px] text-sm">
                    <thead class="bg-gray-50 text-gray-500 border-b border-gray-100">
                        <tr>
                            @php
                            $sortLink = fn($field) => request()->fullUrlWithQuery([
                            'sort' => $field,
                            'dir' => request('sort') === $field && request('dir', 'asc') === 'asc' ? 'desc' : 'asc',
                            ]);
                            $sortIcon = fn($field) => request('sort') === $field
                            ? (request('dir', 'asc') === 'asc' ? '↑' : '↓')
                            : '';
                            @endphp

                            <th class="py-4 px-6 font-bold select-none">
                                <a href="{{ $sortLink('name') }}" class="flex items-center gap-1 hover:text-gray-700">
                                    <span>اسم ولي الأمر</span>
                                    <span class="text-xs text-gray-400">{{ $sortIcon('name') }}</span>
                                </a>
                            </th>
                            <th class="py-4 px-6 font-bold select-none">
                                <a href="{{ $sortLink('email') }}" class="flex items-center gap-1 hover:text-gray-700">
                                    <span>البريد الإلكتروني</span>
                                    <span class="text-xs text-gray-400">{{ $sortIcon('email') }}</span>
                                </a>
                            </th>
                            <th class="py-4 px-6 font-bold text-center w-40">
                                <span>الطلاب المرتبطين</span>
                            </th>
                            <th class="py-4 px-6 font-bold text-center select-none w-32">
                                <a href="{{ $sortLink('status') }}" class="flex items-center justify-center gap-1 hover:text-gray-700">
                                    <span>الحالة</span>
                                    <span class="text-xs text-gray-400">{{ $sortIcon('status') }}</span>
                                </a>
                            </th>
                            <th class="py-4 px-6 font-medium w-28"></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-50">
                        @forelse($guardians as $guardian)
                        <tr class="hover:bg-gray-50/60 transition-colors">
                            {{-- الاسم --}}
                            <td class="py-4 px-6 font-bold text-gray-800">{{ $guardian->name }}</td>

                            {{-- البريد الإلكتروني --}}
                            <td class="py-4 px-6 text-gray-500 font-mono text-xs">{{ $guardian->email ?: '—' }}</td>

                            {{-- الطلاب المرتبطين --}}
                            <td class="py-4 px-6 text-center">
                                @php
                                $count = $guardian->students_count ?? 0;
                                $label = match(true) {
                                $count === 0 => 'لا يوجد طلاب',
                                $count === 1 => 'طالب واحد',
                                $count === 2 => 'طالبان',
                                $count >= 3 && $count <= 10=> $count . ' طلاب',
                                    default => $count . ' طالب',
                                    };
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-1 bg-purple-50 text-purple-700 border border-purple-100 rounded-lg text-xs font-bold">
                                        {{ $label }}
                                    </span>
                            </td>

                            {{-- الحالة والتبديل --}}
                            <td class="py-4 px-6 text-center">
                                @can('update', $guardian)
                                <form method="POST" action="{{ route('guardians.toggleStatus', $guardian) }}"
                                    @submit.prevent="
                                        processing = true;
                                        Swal.fire({
                                            title: 'تغيير حالة حساب ولي الأمر؟',
                                            icon: 'question',
                                            showCancelButton: true,
                                            confirmButtonColor: '#0a5c36',
                                            cancelButtonColor: '#6b7280',
                                            confirmButtonText: 'نعم، تغيير',
                                            cancelButtonText: 'إلغاء',
                                            customClass: {
                                                popup: 'rounded-3xl font-bold',
                                                confirmButton: 'rounded-xl px-6 py-2.5 text-sm',
                                                cancelButton: 'rounded-xl px-6 py-2.5 text-sm',
                                            }
                                        }).then((result) => {
                                            processing = false;
                                            if (result.isConfirmed) $event.target.submit();
                                        })">
                                    @csrf @method('PATCH')
                                    <button type="submit"
                                        :disabled="processing"
                                        class="inline-flex items-center gap-1 px-3 py-1 rounded-xl text-xs font-bold transition-all shadow-sm border disabled:opacity-50 disabled:cursor-not-allowed
                                            {{ $guardian->status === 'active'
                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100'
                                                : 'bg-orange-50 text-orange-600 border-orange-200 hover:bg-orange-100' }}">
                                        <span class="w-1.5 h-1.5 rounded-full inline-block {{ $guardian->status === 'active' ? 'bg-emerald-500' : 'bg-orange-400' }}"></span>
                                        <span>{{ $guardian->status === 'active' ? 'نشط' : 'متوقف' }}</span>
                                    </button>
                                </form>
                                @else
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-xl text-xs font-bold border
                                    {{ $guardian->status === 'active' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-gray-50 text-gray-400 border-gray-100' }}">
                                    {{ $guardian->status === 'active' ? 'نشط' : 'متوقف' }}
                                </span>
                                @endcan
                            </td>

                            {{-- العمليات الإجرائية --}}
                            <td class="py-4 px-6">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('guardians.show', $guardian) }}"
                                        class="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-xl transition-all"
                                        title="عرض" aria-label="عرض حساب ولي الأمر">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>

                                    @can('update', $guardian)
                                    <a href="{{ route('guardians.edit', $guardian) }}"
                                        class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all shadow-inner"
                                        title="تعديل" aria-label="تعديل حساب ولي الأمر">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    @endcan

                                    @can('delete', $guardian)
                                    <form method="POST" action="{{ route('guardians.destroy', $guardian) }}"
                                        @submit.prevent="
                                            processing = true;
                                            Swal.fire({
                                                title: 'حذف حساب ولي الأمر: {{ $guardian->name }}',
                                                text: 'سيتم حذف حساب ولي الأمر نهائياً من النظام. لن تتمكن من التراجع عن هذا الإجراء!',
                                                icon: 'warning',
                                                showCancelButton: true,
                                                confirmButtonColor: '#dc2626',
                                                cancelButtonColor: '#6b7280',
                                                confirmButtonText: 'نعم، احذف',
                                                cancelButtonText: 'إلغاء',
                                                reverseButtons: true,
                                                customClass: {
                                                    popup: 'rounded-3xl font-bold',
                                                    confirmButton: 'rounded-xl px-6 py-2.5 text-sm',
                                                    cancelButton: 'rounded-xl px-6 py-2.5 text-sm',
                                                }
                                            }).then((result) => {
                                                processing = false;
                                                if (result.isConfirmed) $event.target.submit();
                                            })">
                                        @csrf @method('DELETE')
                                        <button type="submit" :disabled="processing"
                                            class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all shadow-inner disabled:opacity-50 disabled:cursor-not-allowed"
                                            title="حذف" aria-label="حذف حساب ولي الأمر">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center text-gray-400 font-medium">
                                <div class="text-4xl mb-2">👥</div>
                                @if(request()->anyFilled(['q', 'status', 'center_id']))
                                <span>لا توجد سجلات لأولياء أمور تطابق الفلاتر المحددة.</span>
                                @else
                                <span>لا يوجد أولياء أمور مسجلون في النظام حالياً.</span>
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- ─── الترقيم الموحّد (Server-side) ─── --}}
            <x-pagination :paginator="$guardians" />
        </div>
    </div>

    @push('scripts')
    <script src="{{ asset('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>
    <script>
        @if(session('success'))
        document.addEventListener('DOMContentLoaded', () => {
            Swal.fire({
                icon: 'success',
                title: 'تم بنجاح',
                text: "{{ session('success') }}",
                confirmButtonColor: '#0a5c36',
                confirmButtonText: 'حسناً',
                timer: 3000,
                timerProgressBar: true,
                customClass: {
                    popup: 'rounded-3xl font-bold',
                    confirmButton: 'rounded-xl px-6 py-2.5 text-sm'
                }
            });
        });
        @endif

        @if(session('error'))
        document.addEventListener('DOMContentLoaded', () => {
            Swal.fire({
                icon: 'error',
                title: 'خطأ في العملية',
                text: "{{ session('error') }}",
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'حسناً',
                customClass: {
                    popup: 'rounded-3xl font-bold',
                    confirmButton: 'rounded-xl px-6 py-2.5 text-sm'
                }
            });
        });
        @endif
    </script>
    @endpush
</x-layouts.markaz-layout>