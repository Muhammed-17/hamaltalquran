<x-layouts.markaz-layout>
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="bg-[#0b3d2c] rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden shadow-xl">
            <h1 class="text-2xl font-black">إضافة محتوى للصفحة الرئيسية</h1>
            <p class="text-emerald-100/80 text-sm mt-1">صورة سلايدر أو صورة معرض أو فيديو يوتيوب.</p>
        </div>

        <form method="POST" action="{{ route('welcome-media.store') }}" enctype="multipart/form-data"
            class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 lg:p-8">
            @csrf
            @include('admin.welcome_media._form')
            <div class="flex items-center gap-3 mt-8">
                <button type="submit" class="px-6 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-bold rounded-2xl transition shadow-lg">حفظ المحتوى</button>
                <a href="{{ route('welcome-media.index') }}" class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-bold rounded-2xl transition">إلغاء</a>
            </div>
        </form>
    </div>
</x-layouts.markaz-layout>
