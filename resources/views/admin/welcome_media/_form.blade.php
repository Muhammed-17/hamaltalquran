@php $isEdit = $item->exists; @endphp

<div class="space-y-5" x-data="{ type: '{{ old('type', $item->type?->value ?? 'image') }}', vsource: '{{ old('video_source', $item->video_path ? 'file' : 'link') }}' }">
    <div>
        <label class="block text-xs font-bold text-gray-400 mb-1.5">القسم</label>
        <select name="type" x-model="type" class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
            @foreach(\App\Enums\WelcomeMediaType::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('type', $item->type?->value) === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </select>
        @error('type') <p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-bold text-gray-400 mb-1.5">العنوان (اختياري)</label>
        <input type="text" name="title" value="{{ old('title', $item->title) }}" maxlength="255"
            class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm" placeholder="مثال: حفل تكريم الحفظة">
        @error('title') <p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p> @enderror
    </div>

    {{-- أماكن ظهور الصورة: السلايدر و/أو المعرض --}}
    <div x-show="type === 'image'" class="bg-gray-50 border border-gray-100 rounded-2xl p-4">
        <span class="block text-xs font-bold text-gray-400 mb-2">تظهر الصورة في</span>
        <div class="flex flex-wrap items-center gap-6">
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="in_slider" value="1" @checked(old('in_slider', $item->in_slider)) class="w-4 h-4 accent-emerald-600">
                <span class="text-sm font-bold text-gray-600">السلايدر</span>
            </label>
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="in_gallery" value="1" @checked(old('in_gallery', $item->in_gallery)) class="w-4 h-4 accent-emerald-600">
                <span class="text-sm font-bold text-gray-600">معرض الصور</span>
            </label>
        </div>
        <p class="text-[11px] text-gray-400 mt-2">يمكن اختيار الاثنين معًا — تُرفع الصورة مرة واحدة وتظهر في المكانين.</p>
        @error('in_slider') <p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p> @enderror
    </div>

    {{-- حقل الصورة --}}
    <div x-show="type !== 'video'">
        <label class="block text-xs font-bold text-gray-400 mb-1.5">الصورة {{ $isEdit ? '(اتركها فارغة للإبقاء على الحالية)' : '' }}</label>
        @if($isEdit && $item->imageUrl())
            <img src="{{ $item->imageUrl() }}" alt="" class="w-40 h-24 object-cover rounded-xl border border-gray-100 mb-2">
        @endif
        <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"
            class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
        <p class="text-[11px] text-gray-400 mt-1">الصيغ المسموحة: jpg وjpeg وpng وwebp — بحد أقصى 5 ميجا، ويُفضّل WebP مضغوطة لسرعة الصفحة.</p>
        <p class="mt-2 rounded-xl p-3 text-xs font-bold leading-relaxed" style="background:#eef6ff;border:1px solid #cfe6ff;color:#075985">المقاسات المناسبة — السلايدر: 1600×900 (أفقي). المعرض الأفقي: 1200×900. المعرض العمودي: 900×1200. كل الصور تُعرض في إطارات موحّدة، فتجنّب النصوص المهمة عند الحواف.</p>
        @error('image') <p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p> @enderror
    </div>

    {{-- حقول الفيديو: المصدر (رابط يوتيوب أو ملف محلي) --}}
    <div x-show="type === 'video'" x-cloak class="space-y-4">
        <div>
            <span class="block text-xs font-bold text-gray-400 mb-2">مصدر الفيديو</span>
            <div class="flex flex-wrap items-center gap-6">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="video_source" value="link" x-model="vsource" class="w-4 h-4 accent-emerald-600">
                    <span class="text-sm font-bold text-gray-600">رابط يوتيوب</span>
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="video_source" value="file" x-model="vsource" class="w-4 h-4 accent-emerald-600">
                    <span class="text-sm font-bold text-gray-600">تخزين محلي (رفع ملف)</span>
                </label>
            </div>
        </div>

        <div x-show="vsource === 'link'">
            <label class="block text-xs font-bold text-gray-400 mb-1.5">رابط فيديو يوتيوب</label>
            <input type="text" name="video_url" value="{{ old('video_url', $item->video_url) }}" dir="ltr"
                class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm text-left" placeholder="https://www.youtube.com/watch?v=...">
            <p class="mt-2 rounded-xl p-3 text-xs font-bold leading-relaxed" style="background:#eef6ff;border:1px solid #cfe6ff;color:#075985">يُقبل رابط المشاهدة أو المختصر youtu.be أو Shorts — لا يستهلك من مساحة الاستضافة شيئًا. المقاسات المناسبة — الفيديو العادي: 1920×1080 (أفقي)، الريلز: 1080×1920 (عمودي).</p>
            @error('video_url') <p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p> @enderror
        </div>

        <div x-show="vsource === 'file'" x-cloak>
            <label class="block text-xs font-bold text-gray-400 mb-1.5">ملف الفيديو {{ $isEdit && $item->video_path ? '(يوجد ملف محفوظ — اتركه فارغًا للإبقاء عليه)' : '' }}</label>
            <input type="file" name="video_file" accept=".mp4,.webm"
                class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm bg-white">
            <p class="mt-2 rounded-xl p-3 text-xs font-bold leading-relaxed" style="background:#eef6ff;border:1px solid #cfe6ff;color:#075985">mp4 أو webm بحد أقصى 100 ميجا — يُخزّن على الاستضافة نفسها ويستهلك من مساحتها وباندويثها عند كل مشاهدة. المقاسات المناسبة — الفيديو العادي: 1920×1080 (أفقي)، الريلز: 1080×1920 (عمودي).</p>
            @error('video_file') <p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p> @enderror
        </div>


    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-bold text-gray-400 mb-1.5">ترتيب العرض</label>
            <input type="number" name="sort_order" value="{{ old('sort_order', $item->sort_order ?? 0) }}" min="0" max="9999"
                class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm">
            @error('sort_order') <p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-end pb-1">
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active)) class="w-4 h-4 accent-emerald-600">
                <span class="text-sm font-bold text-gray-600">ظاهر في الصفحة</span>
            </label>
        </div>
    </div>
</div>
