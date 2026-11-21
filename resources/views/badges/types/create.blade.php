<x-layouts.markaz-layout>
    <div dir="rtl" class="bg-[#0b3d2c] rounded-3xl p-6 text-white shadow-xl mb-8">
        <h1 class="text-2xl font-black">إضافة نوع وسام</h1>
    </div>
    <form method="POST" action="{{ route('badges.types.store') }}" enctype="multipart/form-data">
        @csrf
        @include('badges.types.form')
    </form>
</x-layouts.markaz-layout>
