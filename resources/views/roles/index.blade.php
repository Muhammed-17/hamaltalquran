@php
$groupLabels = [
'system' => '⚙️ النظام العام',
'users' => '👥 المستخدمون والأدوار',
'guardians' => '👨‍👩 أولياء الأمور',
'centers' => '🏢 الفروع والمراكز',
'teachers' => '👨‍🏫 المعلمون',
'circles' => '📚 الحلقات',
'students' => '🎓 الطلاب',
'attendance' => '📋 الحضور والغياب',
'subscriptions' => '💳 الاشتراكات والأسعار',
'collection rounds' => '💰 التحصيل',
'group session plans' => '🗓️ خطط الحصص الجماعية',
'student weekly followups' => '📅 المتابعة الأسبوعية للطلاب',
'behavioral notes' => '📝 الملاحظات السلوكية',
'surah tests' => '📖 اختبارات السور',
'competitions' => '🏆 المسابقات',
'levels' => '📊 المستويات',
'examiners' => '🧪 المختبرون',
'external participants' => '👥 المشاركون الخارجون'

];

$protectedRoles = ['admin', 'general_manager', 'manager', 'supervisor', 'teacher', 'examiner', 'guardian'];
@endphp

<x-layouts.markaz-layout>
    <div class="max-w-7xl mx-auto space-y-8">
        @if($errors->any())
        <div class="bg-red-50 text-red-700 p-4 rounded-xl border border-red-200">
            <ul class="list-disc pr-5 space-y-1">
                @foreach($errors->all() as $error)
                <li class="text-sm">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif
        <!-- 
        {{-- ──────────────────────────────────────── --}}
        {{-- Section A: Create New Role               --}}
        {{-- ──────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">
            <h2 class="text-xl font-bold text-[#0a5c36] mb-6">إضافة دور جديد</h2>

            <form method="POST" action="{{ route('admin.roles.store') }}" novalidate>
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">

                    {{-- اسم الدور بالإنجليزية --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            اسم الدور
                            <span class="text-xs text-gray-400 font-normal">(بالإنجليزية للنظام)</span>
                            <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
                            dir="ltr"
                            autocomplete="off"
                            class="w-full rounded-2xl border p-3 text-sm focus:outline-none focus:ring-1
                                focus:ring-[#0a5c36] focus:border-[#0a5c36] transition-all
                                @error('name') border-red-400 bg-red-50 @enderror"
                            placeholder="مثال: accountant, coordinator">
                        @error('name')
                        <p class="text-red-500 text-xs mt-1 flex items-center gap-1">
                            <span>⚠</span> {{ $message }}
                        </p>
                        @enderror
                    </div>

                    {{-- الاسم بالعربية --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            الاسم بالعربية
                            <span class="text-xs text-gray-400 font-normal">(للعرض)</span>
                        </label>
                        <input
                            type="text"
                            name="display_name"
                            value="{{ old('display_name') }}"
                            autocomplete="off"
                            class="w-full rounded-2xl border border-gray-200 p-3 text-sm focus:outline-none focus:ring-1
                                focus:ring-[#0a5c36] focus:border-[#0a5c36] transition-all
                                @error('display_name') bg-red-50 @enderror"
                            placeholder="مثال: محاسب، منسق">
                        @error('display_name')
                        <p class="text-red-500 text-xs mt-1 flex items-center gap-1">
                            <span>⚠</span> {{ $message }}
                        </p>
                        @enderror
                    </div>
                </div>

                {{-- الصلاحيات --}}
                <p class="text-sm font-bold text-gray-700 mb-3">الصلاحيات</p>

                <div class="space-y-4 mb-6">
                    @foreach($permissions as $group => $groupPermissions)
                    <div class="border border-gray-100 rounded-2xl p-4">

                        {{-- رأس المجموعة مع زر تحديد الكل --}}
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-black text-emerald-700">
                                {{ $groupLabels[$group] ?? $group }}
                            </p>
                            <button
                                type="button"
                                onclick="toggleGroup(this, 'new-role-{{ $group }}')"
                                class="text-xs text-gray-400 hover:text-emerald-600 transition-colors underline underline-offset-2">
                                تحديد الكل
                            </button>
                        </div>

                        <div id="new-role-{{ $group }}" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            @foreach($groupPermissions as $permission)
                            <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="permissions[]"
                                    value="{{ $permission->id }}"
                                    class="rounded border-gray-300 text-[#0a5c36] focus:ring-[#0a5c36]"
                                    {{ in_array($permission->id, old('permissions', [])) ? 'checked' : '' }}>
                                <span class="text-sm text-gray-700">
                                    {{ $permission->display_name ?? $permission->name }}
                                </span>
                            </label>
                            @endforeach
                        </div>

                    </div>
                    @endforeach
                </div>

                <button
                    type="submit"
                    class="bg-[#0a5c36] hover:bg-[#084d2d] text-white font-bold rounded-2xl shadow-sm transition-all text-sm px-5 py-2.5">
                    حفظ الدور
                </button>
            </form>
        </div>
        -->

        {{-- ──────────────────────────────────────── --}}
        {{-- Section B: Existing Roles               --}}
        {{-- ──────────────────────────────────────── --}}
        <div class="space-y-6 mt-8">
            @forelse($roles ?? [] as $role)

            @php $isProtected = in_array($role->name, $protectedRoles); @endphp

            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">

                {{-- رأس الدور (اضغط لإظهار/إخفاء الصلاحيات) --}}
                <div
                    onclick="toggleRole({{ $role->id }})"
                    class="flex justify-between items-center cursor-pointer select-none">
                    <h3 class="text-base font-black text-gray-800 flex items-center gap-2">
                        <span id="role-arrow-{{ $role->id }}" class="text-gray-400 transition-transform duration-200">◀</span>
                        <span>
                            صلاحيات دور:
                            <span class="text-emerald-600">{{ $role->display_name ?? $role->name }}</span>
                            @if($role->display_name && $role->display_name !== $role->name)
                            <span class="text-xs text-gray-400 font-normal ml-1">({{ $role->name }})</span>
                            @endif
                        </span>
                    </h3>

                    <div class="flex items-center gap-2">
                        <span class="text-xs bg-gray-100 text-gray-600 px-3 py-1 rounded-full">
                            {{ $role->permissions->count() }} صلاحية نشطة
                        </span>
                        @if($isProtected)
                        <span class="text-xs bg-amber-50 text-amber-600 border border-amber-200 px-3 py-1 rounded-full font-bold">
                            🔒 دور أساسي
                        </span>
                        @endif
                    </div>
                </div>

                {{-- محتوى الصلاحيات (مخفي افتراضياً) --}}
                <div id="role-body-{{ $role->id }}" class="hidden mt-4 pt-3 border-t border-gray-50">
                <form action="{{ route('admin.roles.permissions.update', $role->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="space-y-4 mb-6">
                        @foreach($permissions as $group => $groupPermissions)
                        <div class="border border-gray-100 rounded-2xl p-4">

                            {{-- رأس المجموعة مع زر تحديد الكل --}}
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-xs font-black text-emerald-700">
                                    {{ $groupLabels[$group] ?? $group }}
                                </p>
                                <button
                                    type="button"
                                    onclick="toggleGroup(this, 'role-{{ $role->id }}-{{ $group }}')"
                                    class="text-xs text-gray-400 hover:text-emerald-600 transition-colors underline underline-offset-2">
                                    تحديد الكل
                                </button>
                            </div>

                            <div id="role-{{ $role->id }}-{{ $group }}" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                @foreach($groupPermissions as $permission)
                                @php
                                $isLocked = ($permission->name === 'manage roles' && $role->name === 'admin');
                                $isChecked = in_array($permission->id, $role->permissions->pluck('id')->toArray());
                                @endphp

                                @if($isLocked)
                                {{-- صلاحية مقفلة — تُرسَل دائماً ولا تظهر كـ checkbox --}}
                                <input type="hidden" name="permissions[]" value="{{ $permission->id }}">
                                <div class="flex items-center gap-3 p-3 bg-amber-50 rounded-xl border border-amber-100 opacity-75 cursor-not-allowed select-none">
                                    <span class="text-amber-400 text-xs">🔒</span>
                                    <span class="text-sm text-amber-700 font-medium">
                                        {{ $permission->display_name ?? $permission->name }}
                                    </span>
                                </div>
                                @else
                                <label class="flex items-center gap-3 p-3 bg-gray-50 hover:bg-emerald-50/50 rounded-xl cursor-pointer transition-all select-none border border-transparent hover:border-emerald-100">
                                    <input
                                        type="checkbox"
                                        name="permissions[]"
                                        value="{{ $permission->id }}"
                                        @checked($isChecked)
                                        class="w-4 h-4 text-emerald-600 focus:ring-emerald-500 border-gray-300 rounded-lg">
                                    <span class="text-sm text-gray-700 font-medium">
                                        {{ $permission->display_name ?? $permission->name }}
                                    </span>
                                </label>
                                @endif

                                @endforeach
                            </div>

                        </div>
                        @endforeach
                    </div>

                    {{-- Footer الفورم --}}
                    <div class="flex flex-wrap justify-between items-center gap-3 pt-4 border-t border-gray-50">

                        {{-- زر الحفظ --}}
                        <button
                            type="submit"
                            class="flex items-center gap-2 px-6 py-2.5 bg-[#0a5c36] hover:bg-[#084d2d] text-white font-bold rounded-xl shadow-md transition-all text-xs">
                            ✓ حفظ وتحديث صلاحيات {{ $role->display_name ?? $role->name }}
                        </button>

                        {{-- زر الحذف أو علامة الحماية --}}
                        @if($isProtected)
                        <span class="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-400 rounded-xl text-xs font-bold">
                            🔒 دور أساسي محمي — لا يمكن حذفه
                        </span>
                        @else
                        <button
                            type="button"
                            onclick="confirmDelete({{ $role->id }}, '{{ e($role->display_name ?? $role->name) }}')"
                            class="flex items-center gap-2 px-6 py-2.5 bg-red-50 hover:bg-red-500 text-red-600 hover:text-white border border-red-200 hover:border-red-500 font-bold rounded-xl transition-all text-xs">
                            🗑 حذف الدور
                        </button>
                        @endif

                    </div>
                </form>
                </div>

                {{-- فورم الحذف المخفية — فقط للأدوار غير المحمية --}}
                @if(!$isProtected)
                <form
                    id="delete-form-{{ $role->id }}"
                    action="{{ route('admin.roles.destroy', $role->id) }}"
                    method="POST"
                    class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
                @endif

            </div>

            @empty
            <div class="bg-white rounded-2xl border border-gray-100 p-12 text-center text-gray-400">
                <p class="text-4xl mb-3">🎭</p>
                <p class="font-bold">لا توجد أدوار بعد</p>
            </div>
            @endforelse
        </div>

    </div>

    @push('scripts')
    <script>
        // ─── إظهار / إخفاء صلاحيات الدور ─────────────
        function toggleRole(id) {
            const body  = document.getElementById('role-body-' + id);
            const arrow = document.getElementById('role-arrow-' + id);
            if (!body || !arrow) return;

            body.classList.toggle('hidden');
            arrow.style.transform = body.classList.contains('hidden')
                ? 'rotate(0deg)'
                : 'rotate(-90deg)';
        }

        // ─── حذف الدور ───────────────────────────────
        function confirmDelete(roleId, roleName) {
            Swal.fire({
                title: 'حذف دور: ' + roleName,
                text: 'سيتم حذف الدور وجميع صلاحياته. لن تتمكن من التراجع!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'نعم، احذف الدور',
                cancelButtonText: 'إلغاء',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-3xl font-bold',
                    confirmButton: 'rounded-xl px-6 py-2.5 text-sm',
                    cancelButton: 'rounded-xl px-6 py-2.5 text-sm',
                }
            }).then(result => {
                if (result.isConfirmed) {
                    document.getElementById('delete-form-' + roleId).submit();
                }
            });
        }

        // ─── تحديد / إلغاء تحديد مجموعة صلاحيات ────
        function toggleGroup(btn, groupId) {
            const container = document.getElementById(groupId);
            if (!container) return;

            const checkboxes = container.querySelectorAll('input[type="checkbox"]');
            const allChecked = [...checkboxes].every(cb => cb.checked);

            checkboxes.forEach(cb => cb.checked = !allChecked);
            btn.textContent = allChecked ? 'تحديد الكل' : 'إلغاء التحديد';
        }
    </script>
@endpush
</x-layouts.markaz-layout>