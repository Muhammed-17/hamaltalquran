<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'مركز حملة القرآن'))</title>

    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}?v=1.1">

    <link href="{{ asset('css/cairo.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link href="{{ asset('vendor/tom-select/tom-select.css') }}" rel="stylesheet">
    <script src="{{ asset('vendor/tom-select/tom-select.complete.min.js') }}"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="font-[Cairo] bg-gray-50">

    <div class="min-h-screen flex flex-col md:flex-row">

        <header class="md:hidden bg-[#0a5c36] text-white flex justify-between items-center p-4 shadow-md">
            <div class="flex items-center gap-2">
                <img src="{{ asset('images/logo.png') }}?v=1.1" alt="Logo" class="w-10 h-10 object-contain">
                <h1 class="font-bold text-lg">مركز حملة القرآن</h1>
            </div>
            <button id="menu-toggle" class="text-3xl focus:outline-none">☰</button>
        </header>

        <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 hidden z-40 md:hidden"></div>

        <aside id="sidebar"
            class="w-52 bg-[#0a5c36] text-white min-h-screen fixed md:relative top-0 right-0 transform translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-50 p-6 space-y-6">

            <div class="flex justify-end md:hidden mb-2">
                <button id="close-menu" class="text-3xl focus:outline-none">×</button>
            </div>

            <div class="text-center">
                <div class="w-20 h-20 mx-auto bg-white/10 rounded-2xl flex items-center justify-center p-2 mb-1">
                    <img src="{{ asset('images/logo.png') }}?v=1.1" alt="Logo" class="w-full h-full object-contain">
                </div>
                <h1 class="text-lg font-black text-orange-400">مركز حملة القرآن</h1>
                <p class="text-xs text-blue-400 mt-1">بناء - إتقان - إبداع</p>
            </div>

            <nav class="space-y-2 text-sm">

                <!-- ═══════════════════════════════════════════════════════════════ -->
                <!-- ═══════════════ قسم ولي الأمر (Guardian) ═════════════════════ -->
                <!-- ═══════════════════════════════════════════════════════════════ -->
                @if (Auth::user()->hasRole('guardian'))
                <a href="{{ route('guardian.dashboard.own') }}"
                    class="block px-4 py-2 rounded-lg {{ request()->routeIs('guardian.dashboard') ? 'bg-[#0d7a48]' : 'hover:bg-[#0d7a48]' }}">
                    المنصة العامة
                </a>
                @can('view own attendance')
                <a href="{{ route('guardian.attendance.own') }}"
                    class="block px-4 py-2 rounded-lg {{ request()->routeIs('guardian.attendance.own') ? 'bg-[#0d7a48]' : 'hover:bg-[#0d7a48]' }}">
                    سجل الحضور
                </a>
                @endcan
                @can('view own subscriptions')
                <a href="{{ route('guardian.subscription.own') }}"
                    class="block px-4 py-2 rounded-lg {{ request()->routeIs('guardian.subscription.own') ? 'bg-[#0d7a48]' : 'hover:bg-[#0d7a48]' }}">
                    الاشتراكات
                </a>
                @endcan
                @can('view notifications')
                <a href="{{ route('guardian.notifications.index') }}"
                    class="block px-4 py-2 rounded-lg {{ request()->routeIs('guardian.notifications.*') ? 'bg-[#0d7a48]' : 'hover:bg-[#0d7a48]' }}">
                    <div class="flex items-center justify-between">
                        <span>الإشعارات</span>
                        @php $unreadCount = auth()->user()->unreadNotifications()->count(); @endphp
                        @if ($unreadCount > 0)
                        <span class="bg-red-500 text-white text-xs px-2 py-0.5 rounded-full">{{ $unreadCount }}</span>
                        @endif
                    </div>
                </a>
                @endcan
                @can('edit profile')
                <a href="{{ route('profile.edit') }}"
                    class="block px-4 py-2 rounded-lg {{ request()->routeIs('profile.*') ? 'bg-[#0d7a48]' : 'hover:bg-[#0d7a48]' }}">
                    الملف الشخصي
                </a>
                @endcan

                @else
                <!-- ═══════════════════════════════════════════════════════════════ -->
                <!-- ═══════════════ القسم العام (Non-Guardian) ═════════════════════ -->
                <!-- ═══════════════════════════════════════════════════════════════ -->

                @if (Auth::user()->hasRole('admin'))
                <a href="{{ route('dashboard') }}"
                    class="block px-4 py-2 rounded-lg {{ request()->routeIs('dashboard') ? 'bg-[#0d7a48]' : 'hover:bg-[#0d7a48]' }}">
                    لوحة التحكم
                </a>
                @endif

                @can('view students')
                <a href="{{ route('students.index') }}"
                    class="block px-4 py-2 rounded-lg {{ request()->routeIs('students.*') ? 'bg-[#0d7a48]' : 'hover:bg-[#0d7a48]' }}">
                    الطلاب
                </a>
                @endcan

                @can('view teachers')
                <a href="{{ route('teachers.index') }}"
                    class="block px-4 py-2 rounded-lg {{ request()->routeIs('teachers.*') ? 'bg-[#0d7a48]' : 'hover:bg-[#0d7a48]' }}">
                    المعلمون
                </a>
                @endcan

                @can('view circles')
                <a href="{{ route('circles.index') }}"
                    class="block px-4 py-2 rounded-lg {{ request()->routeIs('circles.*') ? 'bg-[#0d7a48]' : 'hover:bg-[#0d7a48]' }}">
                    الحلقات
                </a>
                @endcan

                @can('view attendance')
                <a href="{{ route('attendance.index') }}"
                    class="block px-4 py-2 rounded-lg {{ request()->routeIs('attendance.*') ? 'bg-[#0d7a48]' : 'hover:bg-[#0d7a48]' }}">
                    الحضور والغياب
                </a>
                @endcan

                @can('view subscriptions')
                <a href="{{ route('subscriptions.index') }}"
                    class="block px-4 py-2 rounded-lg {{ request()->routeIs('subscriptions.*') ? 'bg-[#0d7a48]' : 'hover:bg-[#0d7a48]' }}">
                    اشتراكات الطلاب
                </a>
                @endcan

                @can('view collection rounds')
                <a href="{{ route('collection-rounds.index') }}"
                    class="block px-4 py-2 rounded-lg {{ request()->routeIs('collection-rounds.*') ? 'bg-[#0d7a48]' : 'hover:bg-[#0d7a48]' }}">
                    تحصيل الاشتراكات
                </a>
                @endcan

                <!-- ═══════════════════════════════════════════════════════════════ -->
                <!-- ═══════════════ قسم المتابعة   (student-weekly-followups) ══════════════════ -->
                <!-- ═══════════════════════════════════════════════════════════════ -->
                {{-- قسم المتابعة --}}
                @php
                $user = Auth::user();
                $isCircleBoundRole = $user->hasRole(['teacher', 'supervisor']) && !$user->hasRole(['admin', 'general_manager', 'manager']);

                if ($isCircleBoundRole) {
                $circleTypes = app(\App\Services\UserAccessService::class)->teacherCircleTypes($user);
                $canSeeGroupFollowup = $circleTypes->contains('group');
                $canSeeIndividualFollowup = $circleTypes->contains('individual');
                } else {
                // admin / general_manager / manager: يشوفوا الاثنين دايماً (نطاقهم أوسع من حلقاتهم فقط)
                $canSeeGroupFollowup = true;
                $canSeeIndividualFollowup = true;
                }
                @endphp

                @canany(['view student weekly followups', 'view behavioral notes'])
                <div x-data="{ open: {{ request()->routeIs(['student-weekly-followups.*', 'group-session-plans.*', 'behavioral-notes.*']) ? 'true' : 'false' }} }" class="space-y-1">
                    <button @click="open = !open"
                        class="w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-[#0d7a48] transition-colors focus:outline-none {{ request()->routeIs(['student-weekly-followups.*', 'group-session-plans.*', 'behavioral-notes.*']) ? 'bg-[#0d7a48]' : '' }}">
                        <span class="flex items-center gap-2">
                            <span>المتابعة</span>
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="open" x-cloak class="pr-3 space-y-1 border-r border-white/10 mr-1">
                        @can('view student weekly followups')
                        @if($canSeeGroupFollowup)
                        <a href="{{ route('student-weekly-followups.index-group') }}"
                            class="block px-4 py-2 rounded-lg text-[13px] {{ request()->routeIs('student-weekly-followups.index-group') ? 'bg-[#0d7a48] font-bold' : 'hover:bg-[#0d7a48]' }}">
                            المتابعة الجماعية
                        </a>
                        @endif
                        @endcan

                        @can('view student weekly followups')
                        @if($canSeeIndividualFollowup)
                        <a href="{{ route('student-weekly-followups.index-individual') }}"
                            class="block px-4 py-2 rounded-lg text-[13px] {{ request()->routeIs('student-weekly-followups.index-individual') ? 'bg-[#0d7a48] font-bold' : 'hover:bg-[#0d7a48]' }}">
                            المتابعة الفردية
                        </a>
                        @endif
                        @endcan

                        @can('view behavioral notes')
                        <a href="{{ route('behavioral-notes.index') }}"
                            class="block px-4 py-2 rounded-lg text-[13px] {{ request()->routeIs('behavioral-notes.*') ? 'bg-[#0d7a48] font-bold' : 'hover:bg-[#0d7a48]' }}">
                            الملاحظات السلوكية
                        </a>
                        @endcan
                    </div>
                </div>
                @endcanany

                <!-- ═══════════════════════════════════════════════════════════════ -->

                {{-- قسم اختبارات السورة --}}
                @can('view surah tests')
                <div x-data="{ open: {{ request()->routeIs('surah-tests.*') ? 'true' : 'false' }} }" class="space-y-1">
                    <button @click="open = !open"
                        class="w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-[#0d7a48] transition-colors focus:outline-none {{ request()->routeIs('surah-tests.*') ? 'bg-[#0d7a48]' : '' }}">
                        <span class="flex items-center gap-2">
                            <span>اختبارات السورة</span>
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="open" x-cloak class="pr-3 space-y-1 border-r border-white/10 mr-1">
                        <a href="{{ route('surah-tests.index.group') }}"
                            class="block px-4 py-2 rounded-lg text-[13px] {{ request()->routeIs('surah-tests.index.group') ? 'bg-[#0d7a48] font-bold' : 'hover:bg-[#0d7a48]' }}">
                            اختبار جماعي
                        </a>

                        <a href="{{ route('surah-tests.index.individual') }}"
                            class="block px-4 py-2 rounded-lg text-[13px] {{ request()->routeIs('surah-tests.index.individual') ? 'bg-[#0d7a48] font-bold' : 'hover:bg-[#0d7a48]' }}">
                            اختبار فردي
                        </a>
                    </div>
                </div>
                @endcan

                @can('view competitions')
                <div x-data="{ open: {{ request()->routeIs(['competitions.*', 'levels.*', 'examiners.*', 'external-participants.*','tafsir-files.*']) ? 'true' : 'false' }} }" class="space-y-1">
                    <button @click="open = !open"
                        class="w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-[#0d7a48] transition-colors focus:outline-none {{ request()->routeIs(['competitions.*', 'levels.*', 'examiners.*', 'external-participants.*','tafsir-files.*']) ? 'bg-[#0d7a48]' : '' }}">
                        <span class="flex items-center gap-2">
                            <span>المسابقات</span>
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="open" x-cloak class="pr-3 space-y-1 border-r border-white/10 mr-1">

                        <a href="{{ route('competitions.index') }}"
                            class="block px-4 py-2 rounded-lg {{ request()->routeIs('competitions.*') ? 'bg-[#0d7a48]' : 'hover:bg-[#0d7a48]' }}">
                            المسابقة
                        </a>
                        @can('manage competitions')
                        <a href="{{ route('levels.index') }}"
                            class="block px-4 py-2 rounded-lg text-[13px] {{ request()->routeIs('levels.*') ? 'bg-[#0d7a48] font-bold' : 'hover:bg-[#0d7a48]' }}">
                            المستويات
                        </a>

                        <a href="{{ route('tafsir-files.index') }}"
                            class="block px-4 py-2 rounded-lg text-[13px] {{ request()->routeIs('tafsir-files.*') ? 'bg-[#0d7a48] font-bold' : 'hover:bg-[#0d7a48]' }}">
                            ملفات التفسير
                        </a>

                        <a href="{{ route('examiners.index') }}"
                            class="block px-4 py-2 rounded-lg text-[13px] {{ request()->routeIs('examiners.*') ? 'bg-[#0d7a48] font-bold' : 'hover:bg-[#0d7a48]' }}">
                            المختبرون
                        </a>

                        <a href="{{ route('external-participants.index') }}"
                            class="block px-4 py-2 rounded-lg text-[13px] {{ request()->routeIs('external-participants.*') ? 'bg-[#0d7a48] font-bold' : 'hover:bg-[#0d7a48]' }}">
                            المشاركون الخارجيون
                        </a>
                        @endcan
                    </div>
                </div>
                @endcan

                <!-- ═══════════════════════════════════════════════════════════════ -->
                <!-- ═══════════════ قسم لوحة المختبر (Examiner) ═════════════════════ -->
                <!-- ═══════════════════════════════════════════════════════════════ -->
                @if(Auth::user()->hasRole('examiner'))
                <a href="{{ route('examiner.dashboard') }}"
                    class="block px-4 py-2 rounded-lg {{ request()->routeIs('examiner.dashboard') ? 'bg-[#0d7a48]' : 'hover:bg-[#0d7a48]' }}">
                    لوحة تحكم المختبر
                </a>

                <a href="{{ route('examiner.competitions.index') }}"
                    class="block px-4 py-2 rounded-lg {{ request()->routeIs(['examiner.competitions.*', 'examiner.participants.*', 'examiner.exam.*']) ? 'bg-[#0d7a48]' : 'hover:bg-[#0d7a48]' }}">
                    مسابقاتي
                </a>
                @endif

                <!-- ═══════════════════════════════════════════════════════════════ -->
                <!-- ═══════════════ قسم الإعدادات (Settings) ═════════════════════ -->
                <!-- ═══════════════════════════════════════════════════════════════ -->
                @if(auth()->user()->hasAnyRole('admin') || auth()->user()->canAny(['manage guardians', 'edit profile', 'view subscription prices', 'manage roles']))
                <div x-data="{ open: {{ (request()->routeIs('guardians.*') || request()->routeIs('profile.*') || request()->routeIs('centers.*') || request()->routeIs('branches.*')  || request()->routeIs('subscription-prices.*') || request()->routeIs('admin.roles.*'))? 'true' : 'false' }} }" class="space-y-1">
                    <button @click="open = !open"
                        class="w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-[#0d7a48] transition-colors focus:outline-none {{ (request()->routeIs('guardians.*') || request()->routeIs('profile.*') || request()->routeIs('centers.*') || request()->routeIs('branches.*') || request()->routeIs('subscription-prices.*') || request()->routeIs('admin.roles.*')) ? 'bg-[#0d7a48]' : '' }}">
                        <span>الإعدادات</span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="open" x-cloak class="pr-3 space-y-1 border-r border-white/10 mr-1">
                        @can('manage guardians')
                        <a href="{{ route('guardians.index') }}"
                            class="block px-4 py-2 rounded-lg text-[13px] {{ request()->routeIs('guardians.*') ? 'bg-[#0d7a48] font-bold' : 'hover:bg-[#0d7a48]' }}">
                            حسابات أولياء الأمور
                        </a>
                        @endcan
                        @can('edit profile')
                        <a href="{{ route('profile.edit') }}"
                            class="block px-4 py-2 rounded-lg text-[13px] {{ request()->routeIs('profile.*') ? 'bg-[#0d7a48] font-bold' : 'hover:bg-[#0d7a48]' }}">
                            الملف الشخصي
                        </a>
                        @endcan
                        @role('admin')
                        <a href="{{ route('centers.index') }}"
                            class="block px-4 py-2 rounded-lg text-[13px] {{ request()->routeIs('centers.*') ? 'bg-[#0d7a48] font-bold' : 'hover:bg-[#0d7a48]' }}">
                            الفروع
                        </a>
                        <a href="{{ route('branches.index') }}"
                            class="block px-4 py-2 rounded-lg text-[13px] {{ request()->routeIs('branches.*') ? 'bg-[#0d7a48] font-bold' : 'hover:bg-[#0d7a48]' }}">
                            المقرات
                        </a>
                        @endrole
                        @can('view subscription prices')
                        <a href="{{ route('subscription-prices.index') }}"
                            class="block px-4 py-2 rounded-lg text-[13px] {{ request()->routeIs('subscription-prices.*') ? 'bg-[#0d7a48] font-bold' : 'hover:bg-[#0d7a48]' }}">
                            أسعار الاشتراكات
                        </a>
                        @endcan
                        @can('manage roles')
                        <a href="{{ route('admin.roles.index') }}"
                            class="block px-4 py-2 rounded-lg text-[13px] {{ request()->routeIs('admin.roles.*') ? 'bg-[#0d7a48] font-bold' : 'hover:bg-[#0d7a48]' }}">
                            الصلاحيات
                        </a>
                        @endcan
                    </div>
                </div>
                @endif
                @endif

                <div class="pt-6 border-t border-white/10 mt-6">
                    <div class="px-4 py-2 mb-2 text-xs text-orange-200">
                        أهلاً، {{ auth()->user()->name ?? 'مستخدم' }}
                    </div>
                    <form method="POST" action="{{ route('logout') }}" id="logout-form">
                        @csrf
                        <button type="button" onclick="confirmLogout()"
                            class="w-full text-right px-4 py-2 rounded-lg hover:bg-red-600 transition duration-300 flex items-center gap-2 text-red-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            <span>تسجيل الخروج</span>
                        </button>
                    </form>
                </div>
            </nav>
        </aside>

        <main class="flex-1 p-4 md:p-6 transition-all duration-300">
            {{ $slot }}
        </main>
    </div>

    <script>
        const toggle = document.getElementById('menu-toggle');
        const closeBtn = document.getElementById('close-menu');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const body = document.body;

        function openMenu() {
            sidebar.classList.remove('translate-x-full');
            overlay.classList.remove('hidden');
            body.classList.add('overflow-hidden');
        }

        function closeMenu() {
            sidebar.classList.add('translate-x-full');
            overlay.classList.add('hidden');
            body.classList.remove('overflow-hidden');
        }

        toggle.addEventListener('click', openMenu);
        closeBtn.addEventListener('click', closeMenu);
        overlay.addEventListener('click', closeMenu);
    </script>

    {{-- ✅ احتفظ به — يحمّل SweetAlert2 --}}
    <script src="{{ asset('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>

    {{-- ✅ ملفاتك المخصصة --}}
    @vite(['resources/js/confirm-logout.js', 'resources/js/confirm-delete.js', 'resources/js/confirm-success.js', 'resources/js/confirm-round.js'])

    {{-- ✅ عرض رسائل الجلسة --}}
    <script>
        @if(session('success'))
        document.addEventListener('DOMContentLoaded', () => {
            showSuccess("{{ session('success') }}");
        });
        @endif

        @if(session('error'))
        document.addEventListener('DOMContentLoaded', () => {
            showError("{{ session('error') }}");
        });
        @endif
    </script>

    @stack('scripts')
</body>

</html>