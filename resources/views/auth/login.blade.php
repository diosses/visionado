@extends('layouts.app')

@section('content')
<div class="flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold mb-6 text-center">Login Placeholder</h1>

        @if ($errors->any())
            <div class="mb-4 rounded border border-red-300 bg-red-50 text-red-700 p-3 text-sm">
                <ul class="list-disc ml-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="/login" class="space-y-4">
            @csrf
            <label class="block font-semibold">Email:</label>
            <input type="email" name="email" class="w-full border rounded px-3 py-2" placeholder="Selecciona un usuario" required>

            <label class="block font-semibold">Contraseña:</label>
            <input type="password" name="password" class="w-full border rounded px-3 py-2" placeholder="password123" required>

            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded">
                Entrar
            </button>
        </form>

        <div class="mt-6 text-gray-600 text-sm">
            <p>Usuarios de prueba:</p>
            <ul class="list-disc ml-5">
                <li>Admin: admin@prueba.com</li>
                <li>Usuario 1: user1@prueba.com</li>
                <li>Usuario 2: user2@prueba.com</li>
                <li>Usuario 3: user3@prueba.com</li>
            </ul>
            <p class="mt-2">Contraseña: password123</p>
        </div>
    </div>
</div>
@endsection
