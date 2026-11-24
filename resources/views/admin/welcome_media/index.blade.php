<x-layouts.markaz-layout>
    <div class="space-y-6">

        {{-- ─── Header ─── --}}
        <div class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden shadow-xl">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 relative z-10">
                <div>
                    <h1 class="text-3xl font-black">محتوى الصفحة الرئيسية</h1>
                    <p class="text-emerald-100/80 mt-2">السلايدر ومعرض الصور والفيديوهات الظاهرة للزوار في صفحة الترحيب — تُعرض الأقسام النشطة فقط وبالترتيب المحدد.</p>
                </div>
                <a href="{{ route('welcome-media.create') }}"
                    class="shrink-0 inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-bold rounded-2xl transition shadow-lg">
                    + إضافة محتوى
                </a>
            </div>
            <div class="absolute -left-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
        </div>

        {{-- ─── إحصائيات سريعة ─── --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 text-center">
                <div class="text-3xl font-black text-[#0a5c36]">{{ $counts['slider'] }}</div>
                <div class="text-xs font-bold text-gray-400 mt-1">صور السلايدر</div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 text-center">
                <div class="text-3xl font-black text-[#0a5c36]">{{ $counts['gallery'] }}</div>
                <div class="text-xs font-bold text-gray-400 mt-1">صور المعرض</div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 text-center">
                <div class="text-3xl font-black text-[#0a5c36]">{{ $counts['video'] }}</div>
                <div class="text-xs font-bold text-gray-400 mt-1">الفيديوهات</div>
            </div>
        </div>

        {{-- ─── القائمة ─── --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-right">
                    <thead>
                        <tr class="bg-gray-50 text-gray-400 text-xs font-black uppercase">
                            <th class="px-6 py-4">المحتوى</th>
                            <th class="px-6 py-4">القسم</th>
                            <th class="px-6 py-4">الترتيب</th>
                            <th class="px-6 py-4">الحالة</th>
                            <th class="px-6 py-4">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($items as $item)
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        @if($item->type === \App\Enums\WelcomeMediaType::Video)
                                            @if($item->youtubeThumbnailUrl())
                                                <img src="{{ $item->youtubeThumbnailUrl() }}" alt="" class="w-20 h-12 object-cover rounded-xl border border-gray-100" loading="lazy">
                                            @endif
                                        @elseif($item->imageUrl())
                                            <img src="{{ $item->imageUrl() }}" alt="" class="w-20 h-12 object-cover rounded-xl border border-gray-100" loading="lazy">
                                        @endif
                                        <div>
                                            <div class="font-bold text-gray-800 text-sm">{{ $item->title ?: '— بدون عنوان —' }}</div>
                                            @if($item->type === \App\Enums\WelcomeMediaType::Video)
                                                <div class="text-[11px] text-gray-400 mt-0.5" dir="ltr">{{ Str::limit($item->isLocalVideo() ? basename($item->video_path) . ' (محلي)' : $item->video_url, 45) }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @if($item->type === \App\Enums\WelcomeMediaType::Video)
                                        <span class="px-3 py-1 rounded-full text-[11px] font-black bg-red-50 text-red-600">فيديو</span>
                                    @else
                                        <div class="flex flex-wrap gap-1.5">
                                            @if($item->in_slider)
                                                <span class="px-3 py-1 rounded-full text-[11px] font-black bg-emerald-50 text-emerald-700">السلايدر</span>
                                            @endif
                                            @if($item->in_gallery)
                                                <span class="px-3 py-1 rounded-full text-[11px] font-black bg-sky-50 text-sky-700">المعرض</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 font-bold text-gray-600 text-sm">{{ $item->sort_order }}</td>
                                <td class="px-6 py-4">
                                    @if($item->is_active)
                                        <span class="px-3 py-1 rounded-full text-[11px] font-black bg-emerald-50 text-emerald-700">ظاهر</span>
                                    @else
                                        <span class="px-3 py-1 rounded-full text-[11px] font-black bg-gray-100 text-gray-500">مخفي</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('welcome-media.edit', $item) }}" class="px-3 py-1.5 rounded-xl bg-amber-50 text-amber-600 text-xs font-bold hover:bg-amber-100 transition">تعديل</a>
                                        <form method="POST" action="{{ route('welcome-media.destroy', $item) }}"
                                            onsubmit="confirmDelete(event, { name: '{{ e($item->title ?: $item->type->label()) }}', type: 'المحتوى' })">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-red-50 text-red-600 text-xs font-bold hover:bg-red-100 transition">حذف</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-400 font-bold">لا يوجد محتوى بعد — أضف أول صورة أو فيديو.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-layouts.markaz-layout>
