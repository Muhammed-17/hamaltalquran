<x-layouts.markaz-layout>
    <x-slot name="title">الملاحظات السلوكية</x-slot>

    {{-- ─── Header ─── --}}
    <div dir="rtl"
        class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-center shadow-xl gap-6 mb-8">
        <div class="text-right w-full md:w-auto z-10">
            <h1 class="text-3xl font-black mb-2">الملاحظات السلوكية</h1>
            <p class="text-emerald-100/80 text-sm font-medium">
                @if(request()->anyFilled(['q', 'center_id', 'circle_id', 'teacher_id', 'status']))
                {{ $behavioralNotes->total() }} نتيجة
                @else
                {{ $behavioralNotes->total() }} ملاحظة مسجلة في النظام
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-4 w-full md:w-auto z-10">
            @can('create behavioral notes')
            <a href="{{ route('behavioral-notes.create') }}"
                class="w-full md:w-auto px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-2xl flex items-center justify-center gap-2 transition-all shadow-lg hover:shadow-emerald-500/20 active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                ملاحظة جديدة
            </a>
            @endcan
        </div>

        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
    </div>

    {{-- ─── فلاتر التصفية ─── --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6">
        <form action="{{ route('behavioral-notes.index') }}" method="GET" class="flex flex-col lg:flex-row gap-4 items-end" dir="rtl">

            {{-- البحث --}}
            <div class="w-full lg:flex-1">
                <label for="filter_q" class="block text-xs font-bold text-gray-400 mb-1.5">البحث باسم الطالب</label>
                <input id="filter_q" type="search" name="q" value="{{ request('q') }}"
                    placeholder="ابحث باسم الطالب..."
                    class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-1 focus:ring-[#0a5c36] focus:border-[#0a5c36] transition-all bg-white text-right">
            </div>

            @if(auth()->user()->hasAnyRole(['admin', 'general_manager']))
            {{-- فلتر المركز --}}
            <div class="w-full lg:w-48">
                <label for="filter_center_id" class="block text-xs font-bold text-gray-400 mb-1.5">المركز</label> {{-- ← عدّلتها من "الفرع" لـ "المركز" --}}
                <x-searchable-select
                    name="center_id"
                    :options="collect($centers ?? [])->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values()"
                    :defaultValue="request('center_id', '')"
                    placeholder="كل المراكز"
                    searchPlaceholder="ابحث عن مركز..." />
            </div>
            @endif

            @if(auth()->user()->hasAnyRole(['supervisor', 'manager', 'general_manager', 'admin']))
            {{-- فلتر الفرع (المقر) --}}
            <div class="w-full lg:w-48" x-data="behavioralNotesFilters()">
                <label for="filter_branch_id" class="block text-xs font-bold text-gray-400 mb-1.5">الفرع</label>
                <x-searchable-select
                    name="branch_id"
                    :options="collect($branches ?? [])->map(fn($b) => ['value' => $b->id, 'label' => $b->name])->values()"
                    :defaultValue="request('branch_id', '')"
                    placeholder="كل الفروع"
                    searchPlaceholder="ابحث عن فرع..." />
            </div>

            {{-- فلتر الحلقة --}}
            <div class="w-full lg:w-48">
                <label for="filter_circle_id" class="block text-xs font-bold text-gray-400 mb-1.5">الحلقة</label>
                <x-searchable-select
                    name="circle_id"
                    :options="collect($circles ?? [])->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values()"
                    :defaultValue="request('circle_id', '')"
                    placeholder="كل الحلقات"
                    searchPlaceholder="ابحث عن حلقة..." />
            </div>

            {{-- فلتر المعلم --}}
            <div class="w-full lg:w-48">
                ...
            </div>
            @endif

            @if(auth()->user()->hasAnyRole(['supervisor', 'manager', 'general_manager', 'admin']))
            {{-- فلتر الحلقة --}}
            <div class="w-full lg:w-48">
                <label for="filter_circle_id" class="block text-xs font-bold text-gray-400 mb-1.5">الحلقة</label>
                <x-searchable-select
                    name="circle_id"
                    :options="collect($circles ?? [])->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->values()"
                    :defaultValue="request('circle_id', '')"
                    placeholder="كل الحلقات"
                    searchPlaceholder="ابحث عن حلقة..." />
            </div>

            {{-- فلتر المعلم --}}
            <div class="w-full lg:w-48">
                <label for="filter_teacher_id" class="block text-xs font-bold text-gray-400 mb-1.5">المعلم</label>
                <x-searchable-select
                    name="teacher_id"
                    :options="collect($teachers ?? [])->map(fn($t) => ['value' => $t->id, 'label' => $t->user->name ?? $t->name])->values()"
                    :defaultValue="request('teacher_id', '')"
                    placeholder="كل المعلمين"
                    searchPlaceholder="ابحث عن معلم..." />
            </div>
            @endif

            {{-- فلتر الحالة --}}
            <div class="w-full lg:w-48">
                <label for="filter_status" class="block text-xs font-bold text-gray-400 mb-1.5">الحالة</label>
                <select id="filter_status" name="status"
                    class="w-full p-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-1 focus:ring-[#0a5c36] focus:border-[#0a5c36] transition-all bg-white appearance-none">
                    <option value="">كل الحالات</option>
                    @foreach(\App\Models\BehavioralNote::STATUSES as $value => $label)
                    <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- زر البحث --}}
            <button type="submit"
                class="w-full lg:w-auto px-5 py-2.5 bg-[#0a5c36] hover:bg-[#08492a] text-white font-bold rounded-xl text-sm transition-all text-center">
                بحث
            </button>

            {{-- زر مسح الفلاتر --}}
            @if(request()->anyFilled(['q', 'center_id', 'circle_id', 'teacher_id', 'status']))
            <a href="{{ route('behavioral-notes.index') }}"
                class="w-full lg:w-auto px-5 py-2.5 bg-gray-50 hover:bg-gray-100 text-gray-500 hover:text-gray-700 font-bold border border-gray-200 rounded-xl text-sm transition-all text-center">
                مسح الفلاتر
            </a>
            @endif
        </form>
    </div>


    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-bold text-lg text-gray-800"><i class="fas fa-clipboard-check ml-2 text-amber-500"></i>الملاحظات السلوكية</h3>
        </div>
        @if(isset($behavioralNotes) && $behavioralNotes->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">#</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الطالب</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الحلقة</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">المعلم</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">السلوك</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">التاريخ</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الحالة</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($behavioralNotes as $note)
                    @php
                    $statusColors = [
                    \App\Models\BehavioralNote::STATUS_PENDING => 'bg-amber-50 text-amber-700',
                    \App\Models\BehavioralNote::STATUS_UNDER_REVIEW => 'bg-sky-50 text-sky-700',
                    \App\Models\BehavioralNote::STATUS_ACTION_TAKEN => 'bg-green-50 text-green-700',
                    ];
                    $statusLabel = \App\Models\BehavioralNote::STATUSES[$note->status] ?? $note->status;
                    $statusClass = $statusColors[$note->status] ?? 'bg-gray-50 text-gray-700';
                    @endphp
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-3 text-gray-500">{{ $note->id }}</td>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $note->student->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $note->circle->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $note->teacher->user->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ Str::limit($note->behavior, 35) }}</td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $note->incident_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">
                            <span class="{{ $statusClass }} px-2.5 py-1 rounded-full text-xs font-medium">{{ $statusLabel }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                @can('view behavioral notes')
                                <a href="{{ route('behavioral-notes.show', $note) }}" class="w-7 h-7 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center"><i class="fas fa-eye text-xs"></i></a>
                                @endcan
                                @can('edit behavioral notes')
                                <a href="{{ route('behavioral-notes.edit', $note) }}" class="w-7 h-7 bg-amber-50 hover:bg-amber-100 text-amber-600 rounded-lg flex items-center justify-center"><i class="fas fa-edit text-xs"></i></a>
                                @endcan
                                @can('approve behavioral notes')
                                <a href="{{ route('behavioral-notes.edit-action', $note) }}" class="w-7 h-7 bg-sky-50 hover:bg-sky-100 text-sky-600 rounded-lg flex items-center justify-center" title="تسجيل الإجراء"><i class="fas fa-clipboard-check text-xs"></i></a>
                                @endcan
                                @can('delete behavioral notes')
                                <form action="{{ route('behavioral-notes.destroy', $note) }}" method="POST" class="inline" id="delete-note-{{ $note->id }}">
                                    @csrf @method('DELETE')
                                    <button type="button"
                                        onclick="confirmDelete(event, { name: '{{ e($note->student->name ?? 'الملاحظة') }} - {{ $note->incident_at?->format('Y-m-d') }}', type: 'الملاحظة السلوكية', form: document.getElementById('delete-note-{{ $note->id }}') })"
                                        class="w-7 h-7 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-gray-100">{{ $behavioralNotes->links() }}</div>
        @else
        <div class="text-center py-16">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3"><i class="fas fa-clipboard-check text-2xl text-gray-300"></i></div>
            <h4 class="text-gray-500 font-medium">لا توجد ملاحظات</h4>
            <p class="text-gray-400 text-sm">سجل ملاحظات سلوكية للطلاب</p>
        </div>
        @endif
    </div>
    <script>
        function behavioralNotesFilters() {
            return {
                init() {
                    window.addEventListener('searchable-change', (e) => {
                        if (e.detail.name === 'center_id') {
                            this.fetchBranches(e.detail.value);
                        }
                    });
                },
                async fetchBranches(centerId) {
                    try {
                        const url = `{{ route('branches.for-center') }}` + (centerId ? `?center_id=${centerId}` : '');
                        const response = await fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                        });
                        const data = await response.json();
                        const formatted = (data.branches || []).map(b => ({
                            value: b.id,
                            label: b.name
                        }));
                        window.dispatchEvent(new CustomEvent('update-options', {
                            detail: {
                                name: 'branch_id',
                                options: formatted
                            }
                        }));
                        window.dispatchEvent(new CustomEvent('clear-selection', {
                            detail: {
                                name: 'branch_id'
                            }
                        }));
                    } catch (error) {
                        console.error('Error fetching branches:', error);
                    }
                },
            };
        }
    </script>
</x-layouts.markaz-layout>