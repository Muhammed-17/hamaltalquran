<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link href="{{ asset('css/cairo.css') }}" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Cairo', sans-serif;
        }
    </style>
</head>

<body class="antialiased text-gray-900 bg-gray-50 flex items-center justify-center min-h-screen">
    <div class="w-full bg-white rounded-2xl shadow-lg">
        <!-- Top Slot for Logo or other Header -->
        @if (isset($logo))
        {{ $logo }}
        @else
        <!-- Default Logo Header -->
        <div class="p-8 text-center flex flex-col items-center justify-center">
            <div class="w-2/8 h-2/8 flex items-center justify-center mb-2 p-2">
                <x-application-logo class="w-full h-full object-contain" />
            </div>
            <h1 class="text-3xl font-bold text-orange-500 mb-2 ">مركز حملة القرآن</h1>
            <p class="text-1xl text-blue-700 font-semibold">بناء - إتقان - إبداع</p>
        </div>
        @endif

        <div class="{{ isset($noPadding) ? '' : 'p-8' }}">
            {{ $slot }}
        </div>

        <!-- Default Footer -->
        <div class="bg-gray-50 p-4 text-center border-t border-gray-100">
            <p class="text-xs text-gray-500">&copy; {{ date('Y') }} مركز حملة القرآن. جميع الحقوق محفوظة.</p>
        </div>
    </div>
</body>

</html>