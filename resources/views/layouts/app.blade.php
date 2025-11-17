<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
</head>
<body class="bg-gray-100">
    <div id="app">
        <header class="bg-white shadow p-4 flex items-center justify-between relative">
            <!-- Izquierda: Logo -->
            <div class="flex items-center space-x-2">
                <img src="{{ asset('images/Chileactores_LOGO.png') }}" alt="Logo Chileactores" class="h-10 w-auto">
            </div>
<!-- Centro: Título + botones -->
    <div class="absolute left-1/2 -translate-x-1/2 flex items-center gap-4">
        <h1 class="text-lg font-bold">
            Plataforma de Visionado
        </h1>

        <!-- Botones -->
    <button class="flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white font-semibold py-1 px-3 rounded shadow" data-nav href="{{ route('inicio') }}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
            </svg>
            Inicio
        </button>

    @auth
    @if(Auth::user()->isAdmin())
            <button
                data-nav href="{{ Auth::user()->isAdmin() ? url('/dashboard/admin') : url('/dashboard/user') }}"      
                class="flex items-center gap-2 bg-blue-500 hover:bg-blue-600 text-white font-semibold py-1 px-3 rounded shadow">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                </svg>
                Gestión de Visionadoras
            </button>
    @endif
    @endauth
    </div>

            <!-- Derecha: Menú de usuario (placeholder) -->
            <div class="flex items-center">
                <div class="relative">
            @auth
                    <button id="user-menu-btn" class="flex items-center gap-2 group">
                        <svg xmlns="http://www.w3.org/2000/svg" 
                            class="h-8 w-8 rounded-full bg-gray-200 p-1 text-gray-600" 
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.655 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span class="font-medium hidden sm:inline">{{ Auth::user()->name }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-70 group-hover:opacity-100" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    
                    <div id="user-menu"
                        class="absolute right-0 mt-2 w-48 bg-white border rounded-lg shadow-lg hidden">
                        <a href="javascript:void(0)" class="block px-4 py-2 hover:bg-gray-50">Mi cuenta</a>
                        
                        <!-- Logout -->
                        <a href="{{ route('logout') }}" 
                        data-logout
                        class="block px-4 py-2 hover:bg-gray-50 text-red-600">
                            Cerrar sesión
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                            @csrf
                        </form>
                    </div>
            @else
            <a href="{{ route('login') }}" class="px-3 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Iniciar sesión</a>
            @endauth
                </div>
            </div>
        </header>
        <main class="py-4">
            @yield('content')
            {{-- Modales individuales se incluyen directamente en cada vista según necesidad --}}
        </main>
    </div>

    <!-- Script mínimo para menú de usuario; resto de utilidades vive en resources/js -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const btn = document.getElementById('user-menu-btn');
            const menu = document.getElementById('user-menu');
            if (btn && menu) {
                btn.addEventListener('click', function () {
                    menu.classList.toggle('hidden');
                });
                document.addEventListener('click', function (event) {
                    if (!btn.contains(event.target) && !menu.contains(event.target)) {
                        menu.classList.add('hidden');
                    }
                });
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
