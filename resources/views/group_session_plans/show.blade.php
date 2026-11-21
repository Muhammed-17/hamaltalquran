<x-layouts.markaz-layout>
    <x-slot name="title">تفاصيل جلسة المجموعة</x-slot>

    <div class="max-w-lg mx-auto">
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="bg-blue-500 px-6 py-5">
                <h3 class="text-white font-bold flex items-center gap-2"><i class="fas fa-users"></i>تفاصيل الجلسة</h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-3 border-b border-gray-50">
                        <span class="text-gray-500 text-sm">اسم الجلسة</span>
                        <span class="font-semibold text-gray-800">{{ $groupSessionPlan->session_name }}</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-gray-50">
                        <span class="text-gray-500 text-sm">الحلقة</span>
                        <span class="font-semibold text-gray-800">{{ $groupSessionPlan->circle->name ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-gray-50">
                        <span class="text-gray-500 text-sm">الوقت</span>
                        <span class="font-semibold text-gray-800 flex items-center gap-2"><i class="fas fa-clock text-blue-500"></i>{{ $groupSessionPlan->start_time }} - {{ $groupSessionPlan->end_time }}</span>
                    </div>
                    @if($groupSessionPlan->planned_content)
                    <div class="py-3 border-b border-gray-50">
                        <span class="text-gray-500 text-sm block mb-2">المحتوى المخطط</span>
                        <p class="text-gray-700 text-sm bg-gray-50 p-3 rounded-lg">{{ $groupSessionPlan->planned_content }}</p>
                    </div>
                    @endif
                    @if($groupSessionPlan->completed_content)
                    <div class="py-3 border-b border-gray-50">
                        <span class="text-gray-500 text-sm block mb-2">المحتوى المنجز</span>
                        <p class="text-gray-700 text-sm bg-gray-50 p-3 rounded-lg">{{ $groupSessionPlan->completed_content }}</p>
                    </div>
                    @endif
                    @if($groupSessionPlan->notes)
                    <div class="py-3">
                        <span class="text-gray-500 text-sm block mb-2">ملاحظات</span>
                        <p class="text-gray-700 text-sm bg-gray-50 p-3 rounded-lg">{{ $groupSessionPlan->notes }}</p>
                    </div>
                    @endif
                </div>
                <div class="mt-6 space-y-2">
                    @can('update', $groupSessionPlan)
                    <a href="{{ route('group-session-plans.edit', $groupSessionPlan) }}" class="block w-full bg-amber-500 hover:bg-amber-600 text-white text-center py-2.5 rounded-lg text-sm font-medium transition-colors"><i class="fas fa-edit ml-2"></i>تعديل</a>
                    @endcan
                    <a href="{{ route('group-session-plans.index') }}" class="block w-full bg-[#0a5c36] hover:bg-[#0d7a48] text-white text-center py-2.5 rounded-lg text-sm font-medium transition-colors"><i class="fas fa-arrow-right ml-2"></i>العودة لقائمة الجلسات</a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.markaz-layout>
