<x-guest-layout>
    <div class="text-center py-6">
        <p class="text-7xl font-extrabold text-[#0a5c36]">404</p>
        <h1 class="mt-4 text-2xl font-bold text-gray-900">الصفحة غير موجودة</h1>
        <p class="mt-2 text-gray-600">الرابط الذي تحاول الوصول إليه غير موجود، أو ربما تم نقله أو حذفه.</p>

        <div class="mt-8 flex items-center justify-center gap-3">
            <a href="{{ url('/') }}"
                class="inline-flex items-center rounded-xl bg-[#0a5c36] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#084a2b]">
                العودة للرئيسية
            </a>
            <a href="{{ route('login') }}"
                class="inline-flex items-center rounded-xl border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                تسجيل الدخول
            </a>
        </div>
    </div>
</x-guest-layout>