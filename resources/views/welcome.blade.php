<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'مركز حملة القرآن') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200..1000&display=swap" rel="stylesheet">

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-[Cairo] antialiased bg-gray-50 text-gray-900">
    <div class="relative min-h-screen">

        <!-- Hero Section -->
        <div id="hero" class="relative h-[550px] overflow-hidden">

            <!-- الخلفية -->
            <div class="absolute inset-0 z-0 rounded-sm">
                <img src="{{ asset('images/hero.png') }}" alt="Markaz Hero" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-l from-[#0a5c36]/90 via-[#0a5c36]/70 to-transparent"></div>
            </div>

            <!-- Header الثابت -->
            <nav class="fixed top-0 left-0 right-0 z-50 bg-[#0a5c36]/90 backdrop-blur-md border-b border-emerald-900/20 shadow-md">
                <div class="flex justify-between items-center px-6 lg:px-20 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center shadow-lg">
                            <x-application-logo class="w-11 h-11 text-[#0a5c36] fill-current" />
                        </div>
                        <span class="text-xl font-extrabold text-white">مركز حملة القرآن</span>
                    </div>

                    <div class="flex items-center gap-4">
                        @if (Route::has('login'))
                        @auth
                        <a href="{{ url('/dashboard') }}"
                            class="px-6 py-1.5 bg-white text-[#0a5c36] font-bold rounded-xl hover:bg-emerald-50 transition-all shadow">
                            لوحة التحكم
                        </a>
                        @else
                        <a href="{{ route('login') }}"
                            class="px-6 py-1.5 bg-white text-[#0a5c36] font-bold rounded-xl hover:bg-emerald-50 transition-all shadow">
                            تسجيل الدخول
                        </a>
                        @endauth
                        @endif
                    </div>
                </div>
            </nav>

            <!-- Hero Content -->
            <div class="flex flex-col lg:flex-row justify-start items-center w-full h-full px-6 lg:px-20 pt-28 text-center lg:text-right gap-5 lg:gap-7">

                <!-- الشعار -->
                <div>
                    <img src="{{ asset('images/logo.png') }}" alt="شعار مركز حملة القرآن"
                        class="w-40 h-40 lg:w-52 lg:h-52 object-contain rounded-xl drop-shadow-lg hover:scale-105 transition-transform duration-300 mx-auto lg:mx-0">
                </div>

                <!-- النصوص -->
                <div
                    class="relative z-10 flex flex-col items-center lg:items-start justify-center text-white select-none pointer-events-none space-y-4">

                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black leading-tight drop-shadow-2xl">
                        مركز حملة القرآن
                    </h1>

                    <p class="text-base sm:text-lg lg:text-xl font-medium text-emerald-100 max-w-2xl leading-relaxed drop-shadow-lg pb-5">
                        بناء الجيل القرآني المتميز من خلال بيئة تعليمية إبداعية متميزة تدمج بين الأصالة والتكنولوجيا الحديثة.
                    </p>

                    <div class=" pointer-events-auto">
                        <a href="{{ route('login') }}"
                            class="px-8 py-3 bg-orange-400 hover:bg-orange-500 text-white font-extrabold text-base lg:text-lg rounded-2xl transition-all hover:scale-105 active:scale-95 shadow-xl shadow-orange-900/20">
                            ابدأ الآن
                        </a>
                    </div>
                </div>
            </div>
        </div>


        <!-- Features Section -->
        <div id="features" class="max-w-7xl mx-auto px-6 py-32 lg:px-20">
            <div class="text-center mb-20">
                <h2 class="text-4xl font-black text-gray-900 mb-4">لماذا تختار مركز حملة القرآن؟</h2>
                <p class="text-gray-500 font-medium text-lg">نقدم نظاماً تعليمياً متكاملاً يهدف إلى تحقيق أقصى استفادة
                    للطلاب</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div
                    class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 transition-all hover:shadow-xl hover:border-emerald-100 group">
                    <div
                        class="w-20 h-20 bg-emerald-50 rounded-3xl flex items-center justify-center text-emerald-600 mb-8 group-hover:bg-emerald-600 group-hover:text-white transition-all">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4 group-hover:text-emerald-700 transition-colors">
                        إدارة الحلقات</h3>
                    <p class="text-gray-500 leading-relaxed font-medium">نظام ذكي لتوزيع الطلاب على الحلقات ومتابعة
                        تقدمهم في حفظ ومراجعة القرآن الكريم بفعالية عالية.</p>
                </div>

                <!-- Feature 2 -->
                <div
                    class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 transition-all hover:shadow-xl hover:border-emerald-100 group">
                    <div
                        class="w-20 h-20 bg-emerald-50 rounded-3xl flex items-center justify-center text-emerald-600 mb-8 group-hover:bg-emerald-600 group-hover:text-white transition-all">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4 group-hover:text-emerald-700 transition-colors">
                        متابعة الحضور</h3>
                    <p class="text-gray-500 leading-relaxed font-medium">تسجيل ذكي وسريع للحضور والغياب يومياً مع تقارير
                        فورية لأولياء الأمور والمعلمين.</p>
                </div>

                <!-- Feature 3 -->
                <div
                    class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 transition-all hover:shadow-xl hover:border-emerald-100 group">
                    <div
                        class="w-20 h-20 bg-emerald-50 rounded-3xl flex items-center justify-center text-emerald-600 mb-8 group-hover:bg-emerald-600 group-hover:text-white transition-all">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4 group-hover:text-emerald-700 transition-colors">
                        تقارير الإنجاز</h3>
                    <p class="text-gray-500 leading-relaxed font-medium">لوحة تحكم شاملة تعرض إحصائيات دقيقة حول مستوى
                        الحفظ والالتزام لكل طالب وحلقته بشكل تفاعلي.</p>
                </div>

                <!-- Feature 4 -->
                <div
                    class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 transition-all hover:shadow-xl hover:border-emerald-100 group">
                    <div
                        class="w-20 h-20 bg-emerald-50 rounded-3xl flex items-center justify-center text-emerald-600 mb-8 group-hover:bg-emerald-600 group-hover:text-white transition-all">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4 group-hover:text-emerald-700 transition-colors">
                        معلمين متخصصين</h3>
                    <p class="text-gray-500 leading-relaxed font-medium">نمتلك فريقاً من المعلمين المتخصصين في حفظ
                        القرآن الكريم، يتمتعون بالكفاءة والخبرة في تعليم الطلاب بمختلف مستوياتهم.</p>
                </div>

                <!-- Feature 5 -->
                <div
                    class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 transition-all hover:shadow-xl hover:border-emerald-100 group">
                    <div
                        class="w-20 h-20 bg-emerald-50 rounded-3xl flex items-center justify-center text-emerald-600 mb-8 group-hover:bg-emerald-600 group-hover:text-white transition-all">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4 group-hover:text-emerald-700 transition-colors">
                        منهجيات تعلم حديثة</h3>
                    <p class="text-gray-500 leading-relaxed font-medium">نستخدم أحدث الأساليب التعليمية التي تساعد
                        الطلاب على الحفظ بطريقة سهلة وممتعة مع التركيز على التدريب العملي والتطبيق.</p>
                </div>

                <!-- Feature 6 -->
                <div
                    class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 transition-all hover:shadow-xl hover:border-emerald-100 group">
                    <div
                        class="w-20 h-20 bg-emerald-50 rounded-3xl flex items-center justify-center text-emerald-600 mb-8 group-hover:bg-emerald-600 group-hover:text-white transition-all">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4 group-hover:text-emerald-700 transition-colors">
                        مرونة في الأوقات</h3>
                    <p class="text-gray-500 leading-relaxed font-medium">نقدم جداول مرنة تناسب جميع الفئات العمرية
                        وتوفر بيئة تعليمية مريحة تتناسب مع احتياجات الطلاب وأوقات عمل أولياء أمورهم.</p>
                </div>
            </div>
        </div>

        <!-- Contact Section -->
        <div id="contact" class="bg-gradient-to-br from-emerald-50 via-teal-50 to-white py-24 px-6 lg:px-20">
            <div class="max-w-7xl mx-auto">
                <!-- Section Header -->
                <div class="text-center mb-20">
                    <div class="inline-block mb-4">
                        <span
                            class="px-6 py-2 bg-emerald-100 text-emerald-700 rounded-full text-sm font-bold uppercase tracking-wider">
                            تواصل معنا
                        </span>
                    </div>
                    <h2 class="text-5xl font-black text-gray-900 mb-6 leading-tight">
                        نحن في خدمتكم دائماً
                    </h2>
                    <p class="text-gray-600 font-medium text-xl max-w-2xl mx-auto leading-relaxed">
                        تواصل معنا عبر قنوات الاتصال المتعددة، نسعد بالرد على استفساراتكم ومساعدتكم
                    </p>
                </div>

                <!-- Contact Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-5xl mx-auto">
                    <!-- Phone Numbers Card -->
                    <div
                        class="bg-white p-10 rounded-[32px] shadow-lg border-2 border-emerald-100 hover:border-emerald-300 hover:shadow-2xl transition-all duration-300 group relative overflow-hidden">
                        <!-- Decorative Background -->
                        <div
                            class="absolute top-0 right-0 w-32 h-32 bg-emerald-50 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform duration-500">
                        </div>

                        <div class="relative z-10">
                            <div
                                class="w-20 h-20 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-3xl flex items-center justify-center text-white mb-6 group-hover:scale-110 group-hover:rotate-6 transition-all duration-300 mx-auto shadow-lg">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </div>
                            <h3 class="text-2xl font-black text-gray-900 mb-6 text-center">أرقام الاتصال</h3>
                            <div class="space-y-3">
                                <a href="tel:+201014863112"
                                    class="flex items-center justify-center gap-3 px-6 py-4 bg-emerald-50 hover:bg-emerald-100 rounded-2xl transition-all group/phone"
                                    dir="ltr">
                                    <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                    </svg>
                                    <span class="text-emerald-700 font-bold text-lg">+20 10 14863112</span>
                                </a>
                                <a href="tel:+201210248636"
                                    class="flex items-center justify-center gap-3 px-6 py-4 bg-emerald-50 hover:bg-emerald-100 rounded-2xl transition-all group/phone"
                                    dir="ltr">
                                    <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                    </svg>
                                    <span class="text-emerald-700 font-bold text-lg">+20 12 10248636</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Facebook Card -->
                    <div
                        class="bg-white p-10 rounded-[32px] shadow-lg border-2 border-blue-100 hover:border-blue-300 hover:shadow-2xl transition-all duration-300 group relative overflow-hidden">
                        <!-- Decorative Background -->
                        <div
                            class="absolute top-0 right-0 w-32 h-32 bg-blue-50 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform duration-500">
                        </div>

                        <div class="relative z-10">
                            <div
                                class="w-20 h-20 bg-gradient-to-br from-blue-500 to-blue-600 rounded-3xl flex items-center justify-center text-white mb-6 group-hover:scale-110 group-hover:rotate-6 transition-all duration-300 mx-auto shadow-lg">
                                <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                </svg>
                            </div>
                            <h3 class="text-2xl font-black text-gray-900 mb-6 text-center">فيسبوك</h3>
                            <a href="https://www.facebook.com/hmlt.alqran.305033" target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center justify-center gap-3 px-8 py-5 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold text-lg rounded-2xl transition-all shadow-lg hover:shadow-xl hover:scale-105">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z" />
                                </svg>
                                <span>تابعنا على فيسبوك</span>
                            </a>
                        </div>
                    </div>

                    <!-- WhatsApp Card -->
                    <div
                        class="bg-white p-10 rounded-[32px] shadow-lg border-2 border-green-100 hover:border-green-300 hover:shadow-2xl transition-all duration-300 group relative overflow-hidden">
                        <!-- Decorative Background -->
                        <div
                            class="absolute top-0 right-0 w-32 h-32 bg-green-50 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform duration-500">
                        </div>

                        <div class="relative z-10">
                            <div
                                class="w-20 h-20 bg-gradient-to-br from-green-500 to-green-600 rounded-3xl flex items-center justify-center text-white mb-6 group-hover:scale-110 group-hover:rotate-6 transition-all duration-300 mx-auto shadow-lg">
                                <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                                </svg>
                            </div>
                            <h3 class="text-2xl font-black text-gray-900 mb-6 text-center">واتساب</h3>
                            <div class="space-y-3">
                                <a href="https://wa.me/201014863112" target="_blank" rel="noopener noreferrer"
                                    class="flex items-center justify-center gap-3 px-6 py-4 bg-green-50 hover:bg-green-100 rounded-2xl transition-all group/whatsapp"
                                    dir="ltr">
                                    <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                                    </svg>
                                    <span class="text-green-700 font-bold text-lg">+20 10 14863112</span>
                                </a>
                                <a href="https://wa.me/201210248636" target="_blank" rel="noopener noreferrer"
                                    class="flex items-center justify-center gap-3 px-6 py-4 bg-green-50 hover:bg-green-100 rounded-2xl transition-all group/whatsapp"
                                    dir="ltr">
                                    <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                                    </svg>
                                    <span class="text-green-700 font-bold text-lg">+20 12 10248636</span>
                                </a>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Financial Support Section -->
                <div class="mt-16 px-4">
                    <div class="bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 rounded-[40px] p-8 md:p-14 shadow-2xl relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/2"></div>
                        <div class="absolute bottom-0 left-0 w-48 h-48 bg-white/5 rounded-full translate-y-1/2 -translate-x-1/2"></div>

                        <div class="max-w-5xl mx-auto text-center relative z-10">
                            <div class="w-24 h-24 bg-white/20 backdrop-blur-sm rounded-[32px] flex items-center justify-center mx-auto mb-8 shadow-inner border border-white/30">
                                <svg class="w-14 h-14 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>

                            <h2 class="text-4xl md:text-5xl font-black text-white mb-4 tracking-tight">للدعم المادي</h2>
                            <p class="text-lg md:text-xl text-emerald-100 mb-10 leading-relaxed max-w-2xl mx-auto">
                                ساهم في دعم مركز حملة القرآن وكن جزءاً من بناء جيل قرآني متميز
                            </p>

                            <!-- Payment Methods Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10 max-w-3xl mx-auto">

                                <!-- Vodafone Cash -->
                                <div class="bg-white/15 backdrop-blur-md rounded-3xl p-6 border border-white/20 shadow-xl hover:bg-white/20 transition-all">
                                    <div class="flex items-center justify-center gap-3 mb-4">
                                        <div class="w-10 h-10 bg-red-500 rounded-full flex items-center justify-center shadow-lg">
                                            <span class="text-white font-bold text-xs">VC</span>
                                        </div>
                                        <h3 class="text-xl font-bold text-white">فودافون كاش</h3>
                                    </div>
                                    <div class="bg-white/20 rounded-2xl p-4 mb-3">
                                        <p class="text-emerald-100 text-sm mb-1">رقم المحفظة</p>
                                        <p class="text-2xl md:text-3xl font-black text-white tracking-wider" dir="ltr">0101 4863 112</p>
                                    </div>
                                    <p class="text-emerald-100 text-sm">
                                        كود التحويل: <span class="font-bold text-white" dir="ltr">*9*7#</span>
                                    </p>
                                </div>

                                <!-- InstaPay -->
                                <div class="bg-white/15 backdrop-blur-md rounded-3xl p-6 border border-white/20 shadow-xl hover:bg-white/20 transition-all">
                                    <div class="flex items-center justify-center gap-3 mb-4">
                                        <div class="w-10 h-10 bg-indigo-500 rounded-full flex items-center justify-center shadow-lg">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                            </svg>
                                        </div>
                                        <h3 class="text-xl font-bold text-white">انستا باي</h3>
                                    </div>
                                    <div class="bg-white/20 rounded-2xl p-4 mb-3">
                                        <p class="text-emerald-100 text-sm mb-1">الرقم المسجل</p>
                                        <p class="text-2xl md:text-3xl font-black text-white tracking-wider" dir="ltr">0120 3543 688</p>
                                    </div>
                                    <p class="text-emerald-100 text-sm">
                                        التحويل عبر تطبيق <span class="font-bold text-white">InstaPay</span>
                                    </p>
                                </div>

                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                                <a href="tel:+201014863112"
                                    class="w-full sm:w-auto px-8 py-4 bg-white text-teal-700 font-bold text-lg rounded-2xl transition-all hover:scale-105 active:scale-95 shadow-lg hover:shadow-xl flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                    اتصل للتبرع
                                </a>
                                <a href="https://wa.me/201014863112" target="_blank" rel="noopener noreferrer"
                                    class="w-full sm:w-auto px-8 py-4 bg-green-500 text-white font-bold text-lg rounded-2xl transition-all hover:scale-105 active:scale-95 shadow-lg hover:bg-green-600 flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                                    </svg>
                                    واتساب
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="bg-gray-900 text-gray-300 py-10 px-6 lg:px-20">
            <div class="max-w-7xl mx-auto flex flex-col lg:flex-row justify-between items-center gap-10">

                <!-- الشعار والعنوان -->
                <div class="flex flex-col lg:flex-row items-center gap-4">
                    <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center shadow-lg">
                        <x-application-logo class="w-13 h-13 text-[#0a5c36] fill-current" />
                    </div>
                    <div class="text-center lg:text-right">
                        <h2 class="text-2xl font-extrabold text-white mb-1">مركز حملة القرآن</h2>
                        <p class="text-gray-400 text-sm">بناء - إتقان - إبداع</p>
                    </div>
                </div>

                <!-- روابط التنقل -->
                <div class="flex gap-6 text-sm font-medium">
                    <a href="#hero" class="hover:text-white transition-colors">الرئيسية</a>
                    <a href="#features" class="hover:text-white transition-colors">عن المركز</a>
                    <a href="#contact" class="hover:text-white transition-colors">تواصل معنا</a>
                </div>

                <!-- الحقوق -->
                <div class="text-center text-gray-500 text-sm">
                    &copy; {{ date('Y') }} <span class="text-gray-300 font-semibold">مركز حملة القرآن</span>. جميع الحقوق محفوظة.
                </div>

            </div>
        </footer>

    </div>
</body>

</html>