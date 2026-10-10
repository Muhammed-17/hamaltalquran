<x-markaz-layout>
    <x-slot name="title">تعديل سجل الحضور</x-slot>

    <div class="max-w-2xl mx-auto py-8 px-4">

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">تعديل سجل الحضور</h1>

            <form action="{{ route('attendance.update', $attendance) }}" method="POST">
                @csrf
                @method('PUT')

                <!-- Student Info -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-500">الطالب</label>
                            <p class="text-lg font-semibold text-gray-900">{{ $attendance->student->name ?? '-' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">الحلقة</label>
                            <p class="text-lg font-semibold text-gray-900">{{ $attendance->student->circle->name ?? '-' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Date -->
                <div class="mb-4">
                    <label for="date" class="block text-sm font-medium text-gray-700 mb-1">التاريخ</label>
                    <input type="date" name="date" id="date"
                        value="{{ old('date', $attendance->date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                        class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"
                        required>
                    @error('date')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Status -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">حالة الحضور</label>

                    @php
                    $statuses = [
                    'present' => ['حاضر', 'emerald'],
                    'absent' => ['غائب', 'red'],
                    'late' => ['متأخر', 'amber'],
                    'excused' => ['بعذر', 'blue']
                    ];
                    $selectedStatus = old('status') !== null ? old('status') : ($attendance->status ?? 'present');
                    @endphp

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        @foreach($statuses as $value => [$label, $color])
                        @php
                        $isChecked = $selectedStatus == $value;
                        @endphp
                        <label class="cursor-pointer relative block">
                            <input type="radio" name="status" value="{{ $value }}"
                                {{ $isChecked ? 'checked' : '' }}
                                class="hidden peer">
                            <div class="p-3 rounded-lg border-2 text-center transition-all
                                {{ $isChecked ? "border-{$color}-500 bg-{$color}-50" : 'border-gray-200 bg-white hover:border-gray-300' }}">
                                <span class="font-bold {{ $isChecked ? "text-{$color}-600" : 'text-gray-500' }}">{{ $label }}</span>
                            </div>
                        </label>
                        @endforeach
                    </div>
                    @error('status')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Card -->
                <div class="mb-4">
                    <label for="card" class="block text-sm font-medium text-gray-700 mb-1">كروت</label>
                    <input type="number" name="card" id="card" min="0" step="1"
                        value="{{ old('card', $attendance->card ?? 0) }}"
                        class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                    @error('card')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Notes -->
                <div class="mb-6">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
                    <textarea name="notes" id="notes" rows="3"
                        class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"
                        placeholder="أضف ملاحظات إن وجدت...">{{ old('notes', $attendance->notes) }}</textarea>
                    @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Actions -->
                <div class="flex justify-between items-center">
                    <div class="flex gap-3">
                        <a href="{{ route('attendance.index') }}"
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                            إلغاء
                        </a>
                        <button type="submit"
                            class="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition font-bold">
                            حفظ التعديلات
                        </button>
                    </div>
                </div>
            </form>

            @can('delete', $attendance)
            <form id="delete-form" action="{{ route('attendance.destroy', $attendance) }}" method="POST" class="hidden">
                @csrf
                @method('DELETE')
            </form>
            @endcan
        </div>
    </div>

    <script>
        function confirmDelete() {
            if (confirm('هل أنت متأكد من حذف هذا السجل؟ لا يمكن التراجع عن هذا الإجراء.')) {
                document.getElementById('delete-form').submit();
            }
        }
    </script>
</x-markaz-layout>