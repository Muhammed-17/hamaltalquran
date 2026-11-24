@php $isEdit = $item->exists; @endphp

<div class="space-y-5" x-data="{ type: '{{ old('type', $item->type?->value ?? 'image') }}' }">
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
        <p class="text-[11px] text-gray-400 mt-1">الصيغ المسموحة: jpg وjpeg وpng وwebp — بحد أقصى 5 ميجا. يُفضّل WebP مضغوطة لسرعة الصفحة.</p>
        @error('image') <p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p> @enderror
    </div>

    {{-- حقل رابط الفيديو --}}
    <div x-show="type === 'video'" x-cloak>
        <label class="block text-xs font-bold text-gray-400 mb-1.5">رابط فيديو يوتيوب</label>
        <input type="text" name="video_url" value="{{ old('video_url', $item->video_url) }}" dir="ltr"
            class="w-full p-2.5 px-4 border border-gray-200 rounded-xl text-sm text-left" placeholder="https://www.youtube.com/watch?v=...">
        <p class="text-[11px] text-gray-400 mt-1">يُقبل رابط المشاهدة أو الرابط المختصر youtu.be أو روابط Shorts — والفيديو يُضمّن من يوتيوب ولا يُرفع على الموقع.</p>
        @error('video_url') <p class="text-red-500 text-xs mt-1 font-bold">{{ $message }}</p> @enderror
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
