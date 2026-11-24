@if($planType === 'group')
<!-- ═══════════════════════════════════════ -->
<!-- جدول المتابعات الجماعية -->
<!-- ═══════════════════════════════════════ -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-700">
                <tr>
                    <th class="px-4 py-3 text-right font-semibold">#</th>
                    <th class="px-4 py-3 text-right font-semibold">الأسبوع</th>
                    <th class="px-4 py-3 text-right font-semibold">الحلقة</th>
                    <th class="px-4 py-3 text-right font-semibold">المعلم</th>
                    <th class="px-4 py-3 text-right font-semibold">عدد الطلاب</th>
                    <th class="px-4 py-3 text-right font-semibold">تاريخ الإنشاء</th>
                    <th class="px-4 py-3 text-center font-semibold">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($groupBatches as $batch)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-gray-500">{{ $loop->iteration }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#0a5c36] text-white">
                            {{ $batch->week_start->format('Y-m-d') }}
                        </span>
                        <span class="text-xs text-gray-400 block mt-1">إلى {{ $batch->week_end->format('Y-m-d') }}</span>
                    </td>
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $batch->circle->name ?? '-' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $batch->teacher->user->name ?? '-' }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800">
                            {{ $batch->students_count }} طالب
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $batch->created_at->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-center gap-1">
                            @role('admin|general_manager|manager|supervisor|teacher')
                            <a href="{{ route('student-weekly-followups.show-group', $batch->batch_id) }}"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-sky-50 text-sky-600 hover:bg-sky-100 transition-colors"
                                title="عرض">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </a>
                            @endrole
                            @can('edit student weekly followups')
                            <a href="{{ route('student-weekly-followups.edit-group', $batch->batch_id) }}"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 transition-colors"
                                title="تعديل">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                            @endcan
                            @can('delete student weekly followups')
                            <form action="{{ route('student-weekly-followups.destroy-group', $batch->batch_id) }}"
                                method="POST" class="inline"
                                onsubmit="confirmDelete(event, { name: '{{ e($batch->circle->name ?? 'الدفعة') }} - {{ $batch->week_start->format('Y-m-d') }}', type: 'المتابعة الجماعية' })">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors"
                                    title="حذف">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                        <svg class="w-16 h-16 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                        <p class="text-lg font-medium">لا توجد متابعات جماعية</p>
                        <p class="text-sm mt-1">قم بإنشاء متابعة جماعية جديدة</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $groupBatches->links() }}
    </div>
</div>

@else
<!-- ═══════════════════════════════════════ -->
<!-- جدول المتابعات الفردية -->
<!-- ═══════════════════════════════════════ -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-700">
                <tr>
                    <th class="px-4 py-3 text-right font-semibold">#</th>
                    <th class="px-4 py-3 text-right font-semibold">الطالب</th>
                    <th class="px-4 py-3 text-right font-semibold">الحلقة</th>
                    <th class="px-4 py-3 text-right font-semibold">المعلم</th>
                    <th class="px-4 py-3 text-right font-semibold">الأسبوع</th>
                    <th class="px-4 py-3 text-right font-semibold">الحفظ الجديد</th>
                    <th class="px-4 py-3 text-center font-semibold">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($individualPlans as $plan)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-gray-500">{{ $loop->iteration }}</td>
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $plan->student->name ?? '-' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $plan->circle->name ?? '-' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $plan->teacher->user->name ?? '-' }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#0a5c36] text-white">
                            {{ $plan->week_start->format('Y-m-d') }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-600">
                        @if($plan->newMemorizations->first())
                        <span class="text-xs">
                            {{ $plan->newMemorizations->first()->fromSurah->name ?? '' }}
                            ({{ $plan->newMemorizations->first()->plan_from_ayah }})
                            <span class="text-gray-400 mx-1">←</span>
                            {{ $plan->newMemorizations->first()->toSurah->name ?? '' }}
                            ({{ $plan->newMemorizations->first()->plan_to_ayah }})
                        </span>
                        @else
                        <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-center gap-1">
                            @role('admin|general_manager|manager|supervisor|teacher')
                            <a href="{{ route('student-weekly-followups.show', $plan) }}"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-sky-50 text-sky-600 hover:bg-sky-100 transition-colors"
                                title="عرض">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </a>
                            @endrole
                            @can('edit student weekly followups')
                            <a href="{{ route('student-weekly-followups.edit-individual', $plan) }}"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 transition-colors"
                                title="تعديل">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                            @endcan
                            @can('delete student weekly followups')
                            <form action="{{ route('student-weekly-followups.destroy', $plan) }}"
                                method="POST" class="inline"
                                onsubmit="confirmDelete(event, { name: '{{ e($plan->student->name ?? 'الطالب') }} - {{ $plan->week_start->format('Y-m-d') }}', type: 'المتابعة الفردية' })">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors"
                                    title="حذف">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                        <svg class="w-16 h-16 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <p class="text-lg font-medium">لا توجد متابعات فردية</p>
                        <p class="text-sm mt-1">قم بإنشاء متابعة فردية جديدة</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $individualPlans->links() }}
    </div>
</div>
@endif