@if($total > 50)
    <div class="p-4 border-t border-gray-50 text-center" data-load-more-wrap>
        <button type="button" data-load-more data-tab="{{ $tab }}" data-offset="50" data-target="{{ $target }}" data-url="{{ route('students.tab-data', $student) }}"
            class="px-6 py-2.5 bg-[#0a5c36] hover:bg-[#0b3d2c] text-white rounded-xl font-bold text-sm">عرض المزيد</button>
        <span class="block mt-2 text-xs font-bold text-gray-400" data-load-more-count>تم عرض 50 من أصل {{ $total }}</span>
    </div>
@endif
