@php
// ✅ تحديد الأدوار مرة واحدة في PHP لاستخدامها في الـ View
$canViewStudents = auth()->user()->can('view students');

$sortLink = fn($field) => request()->fullUrlWithQuery([
'sort' => $field,
'dir' => request('sort') === $field && request('dir', 'asc') === 'asc' ? 'desc' : 'asc',
]);
$sortIcon = fn($field) => request('sort') === $field
? (request('dir', 'asc') === 'asc' ? '↑' : '↓')
: '';

$hasFilters = request()->anyFilled([
'q', 'status', 'circle_id', 'educational_stage',
'center_id', 'school_grade', 'decision', 'age_min', 'age_max',
]);
@endphp

<x-layouts.markaz-layout>

    <div class="space-y-6">

        {{-- Header --}}
        <div class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6">
            <div class="order-2 md:order-2 flex flex-wrap items-center gap-4 w-full md:w-auto">
                @can('create students')
                <a href="{{ route('students.export', request()->query()) }}"
                    class="w-full md:w-auto px-6 py-3 bg-white/10 hover:bg-white/20 text-white font-bold rounded-2xl flex items-center justify-center gap-2 transition-all border border-white/20 active:scale-95">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H8a2 2 0 01-2-2V5a2 2 0 012-2h6l6 6v11a2 2 0 01-2 2z" />
                    </svg>
                    تصدير Excel
                </a>

                <a href="{{ route('students.excluded-review') }}"
                    class="w-full md:w-auto px-6 py-3 bg-white/10 hover:bg-white/20 text-white font-bold rounded-2xl flex items-center justify-center gap-2 transition-all border border-white/20 active:scale-95">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    مراجعة مستثناة
                </a>

                <a href="{{ route('favorites.index') }}"
                    class="w-full md:w-auto px-6 py-3 bg-yellow-500/90 hover:bg-yellow-400 text-white font-bold rounded-2xl flex items-center justify-center gap-2 transition-all border border-white/20 active:scale-95">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                    </svg>
                    المفضل
                </a>

                <a href="{{ route('students.create') }}"
                    class="w-full md:w-auto px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-2xl flex items-center justify-center gap-2 transition-all shadow-lg hover:shadow-emerald-500/20 active:scale-95">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    جديد
                </a>
                @endcan
            </div>

            <div class="order-1 md:order-1 text-right w-full md:w-auto z-10">
                <h1 class="text-3xl font-black mb-2">إدارة الطلاب</h1>
                <p class="text-emerald-100/80 text-sm font-medium">
                    @if($hasFilters)
                    {{ $students->total() }} نتيجة
                    @else
                    {{ $students->total() }} طالب مسجل في النظام
                    @endif
                </p>
            </div>

            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
        </div>

        {{-- فلاتر (GET form حقيقي) --}}
        <form method="GET" action="{{ route('students.index') }}" class="bg-white p-4 rounded-xl border border-gray-100 space-y-4">

            <div class="flex flex-col lg:flex-row gap-4">
                <div class="flex-1 relative">
                    <input type="search" name="q" value="{{ request('q') }}"
                        placeholder="بحث بالاسم، الكود، الهاتف..."
                        class="w-full px-4 py-2.5 pr-10 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#10b981]/50 focus:border-emerald-500">
                    <svg class="w-5 h-5 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>

                <button type="submit"
                    class="px-6 py-2.5 bg-[#0a5c36] hover:bg-[#08492a] text-white font-bold rounded-lg transition-colors shrink-0">
                    بحث
                </button>

                @if($hasFilters)
                <a href="{{ route('students.index') }}"
                    class="px-5 py-2.5 border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium rounded-lg transition-colors shrink-0 text-center">
                    إعادة تعيين
                </a>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @if(auth()->check() && auth()->user()->hasAnyRole(['admin', 'general_manager', 'manager', 'supervisor']))
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">الحالة</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#10b981]/50">
                        <option value="">الكل</option>
                        <option value="مقيد" @selected(request('status')==='مقيد' )>مقيد</option>
                        <option value="متوقف" @selected(request('status')==='متوقف' )>متوقف</option>
                        <option value="مسافر" @selected(request('status')==='مسافر' )>مسافر</option>
                    </select>
                </div>
                @endif

                @if(auth()->check() && auth()->user()->hasAnyRole(['admin', 'general_manager', 'manager', 'supervisor']))
                @if($circles->count() > 1)
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">الحلقة</label>
                    <x-searchable-select
                        name="circle_id"
                        :options="$circles->map(fn($circle) => ['value' => (string) $circle->id, 'label' => $circle->name])->toArray()"
                        placeholder="كل الحلقات"
                        searchPlaceholder="ابحث عن حلقة..."
                        :defaultValue="request('circle_id', '')" />
                </div>
                @endif
                @endif

                @if(auth()->check() && auth()->user()->hasAnyRole(['admin', 'general_manager', 'manager', 'supervisor','teacher']))
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">المرحلة الدراسية</label>
                    <select name="educational_stage" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#10b981]/50">
                        <option value="">الكل</option>
                        @foreach(['تمهيدي','حضانة','ابتدائي','اعدادي','ثانوي','جامعي','خريج'] as $stage)
                        <option value="{{ $stage }}" @selected(request('educational_stage')===$stage)>{{ $stage }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @role('admin')
                @if($centers->count() > 1)
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">الفرع</label>
                    <select name="center_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#10b981]/50">
                        <option value="">كل الفروع</option>
                        @foreach($centers as $center)
                        <option value="{{ $center->id }}" @selected((string) request('center_id')===(string) $center->id)>
                            {{ $center->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">العمر (من – إلى)</label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="age_min" value="{{ request('age_min') }}"
                            min="1" max="99" placeholder="من"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#10b981]/50">
                        <span class="text-gray-400 shrink-0">–</span>
                        <input type="number" name="age_max" value="{{ request('age_max') }}"
                            min="1" max="99" placeholder="إلى"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#10b981]/50">
                    </div>
                </div>
                @endrole

                @if(auth()->check() && auth()->user()->hasAnyRole(['admin', 'general_manager', 'manager', 'supervisor','teacher']))
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">الصف الدراسي</label>
                    <select name="school_grade" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#10b981]/50">
                        <option value="">كل الصفوف</option>
                        @foreach(['الأول','الثاني','الثالث','الرابع','الخامس','السادس','دراسات عليا','لا يوجد'] as $grade)
                        <option value="{{ $grade }}" @selected(request('school_grade')===$grade)>{{ $grade }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>


            {{-- فلتر قرار الإدارة — للأدوار الإدارية فقط --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @role('admin')
                @if($canViewStudents)
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">قرار الإدارة</label>
                    <select name="decision" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#10b981]/50">
                        <option value="">الكل</option>
                        <option value="تحت الاختبار" @selected(request('decision')==='تحت الاختبار' )>تحت الاختبار</option>
                        <option value="مقبول" @selected(request('decision')==='مقبول' )>مقبول</option>
                        <option value="مرفوض" @selected(request('decision')==='مرفوض' )>مرفوض</option>
                    </select>
                </div>
                @endif
                @endrole
                @if(auth()->check() && auth()->user()->hasAnyRole(['admin', 'general_manager', 'manager', 'supervisor','teacher']))
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">الجنس</label>
                    <select name="gender" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#10b981]/50">
                        <option value="">الكل</option>
                        <option value="male" @selected(request('gender')==='male' )>ذكر</option>
                        <option value="female" @selected(request('gender')==='female' )>أنثى</option>
                    </select>
                </div>
                @endif
            </div>

        </form>

        {{-- Table --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-x-auto">
            <table class="w-full text-right min-w-[900px]">
                <thead class="bg-gray-50 text-gray-500 text-sm">
                    <tr>
                        <th class="py-4 px-6 font-medium rounded-tr-xl select-none">
                            <a href="{{ $sortLink('name') }}" class="flex items-center gap-1 hover:text-gray-700">
                                <span>اسم الطالب</span>
                                <span>{{ $sortIcon('name') }}</span>
                            </a>
                        </th>
                        <th class="py-4 px-6 font-medium select-none">
                            <a href="{{ $sortLink('status') }}" class="flex items-center gap-1 hover:text-gray-700">
                                <span>الحالة</span>
                                <span>{{ $sortIcon('status') }}</span>
                            </a>
                        </th>
                        <th class="py-4 px-6 font-medium select-none">
                            <a href="{{ $sortLink('circle_name') }}" class="flex items-center gap-1 hover:text-gray-700">
                                <span>الحلقة</span>
                                <span>{{ $sortIcon('circle_name') }}</span>
                            </a>
                        </th>
                        <th class="py-4 px-6 font-medium select-none">
                            <a href="{{ $sortLink('educational_stage') }}" class="flex items-center gap-1 hover:text-gray-700">
                                <span>المرحلة الدراسية</span>
                                <span>{{ $sortIcon('educational_stage') }}</span>
                            </a>
                        </th>
                        <th class="py-4 px-6 font-medium select-none">
                            <a href="{{ $sortLink('age') }}" class="flex items-center gap-1 hover:text-gray-700">
                                <span>العمر</span>
                                <span>{{ $sortIcon('age') }}</span>
                            </a>
                        </th>
                        <th class="py-4 px-6 font-medium rounded-tl-xl"></th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @forelse($students as $student)
                    <tr class="hover:bg-gray-50/50">
                        <td class="py-4 px-6 font-medium text-gray-800">{{ $student->name }}</td>
                        <td class="py-4 px-6">
                            @if($student->status === 'مقيد')
                            <span class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-md text-sm">مقيد</span>
                            @elseif($student->status === 'متوقف')
                            <span class="px-3 py-1 bg-orange-100 text-orange-700 rounded-md text-sm">موقوف</span>
                            @elseif($student->status === 'مسافر')
                            <span class="px-3 py-1 bg-cyan-100 text-cyan-700 rounded-md text-sm">مسافر</span>
                            @endif
                        </td>
                        <td class="py-4 px-6 text-gray-600">{{ $student->circle?->name ?? '—' }}</td>
                        <td class="py-4 px-6 text-gray-600">{{ $student->educational_stage ?? '—' }}</td>
                        <td class="py-4 px-6 text-gray-600">{{ $student->date_of_birth?->age ?? '—' }}</td>
                        <td class="py-4 px-6">
                            <div class="flex items-center justify-end gap-3">
                                @can('view', $student)
                                <a href="{{ route('students.show', $student) }}"
                                    class="text-green-400 hover:text-green-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a>
                                @endcan

                                @can('update', $student)
                                <a href="{{ route('students.edit', $student) }}"
                                    class="text-blue-500 hover:text-blue-700 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                                @endcan

                                @php
                                $isFavorited = $student->isFavoritedByCurrentUser();
                                @endphp
                                <button type="button"
                                    onclick="openFavoriteModal({{ $student->id }}, {{ $isFavorited ? 'true' : 'false' }})"
                                    class="{{ $isFavorited ? 'text-yellow-500' : 'text-gray-300' }} hover:text-yellow-500 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                        fill="{{ $isFavorited ? 'currentColor' : 'none' }}" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-12 px-6 text-center text-gray-500">
                            @if($hasFilters)
                            <span>لا توجد نتائج مطابقة لبحثك أو الفلاتر.</span>
                            @else
                            <span>لا يوجد طلاب مسجلون حالياً.</span>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- ─── الترقيم الموحّد (نفس مكوّن Guardians) ─── --}}
            <x-pagination :paginator="$students" />
        </div>
    </div>
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initFavorites({
                admin: @json(auth()->user()->hasRole('admin')),
                teachers: @json($teachers->map(fn($t) => ['id' => $t -> id, 'name' => $t -> user -> name ?? ('معلم #'.$t -> id)])),
                token: @json(csrf_token()),
                baseUrl: @json(url('/students')),
            });
        });
    </script>
    @endpush

</x-layouts.markaz-layout>