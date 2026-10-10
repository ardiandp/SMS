@extends('layouts.app')

@section('title', 'Masuk')

@section('content')
    <div class="max-w-sm mx-auto mt-10 bg-white rounded shadow p-6">
        <h1 class="text-xl font-semibold mb-6">Masuk ke IRNIS School</h1>

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium mb-1">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       class="w-full rounded border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium mb-1">Password</label>
                <input id="password" name="password" type="password" required
                       class="w-full rounded border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:outline-none">
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1" class="rounded border-gray-300">
                Ingat saya
            </label>

            <button type="submit"
                    class="w-full rounded bg-indigo-600 text-white py-2 hover:bg-indigo-700">
                Masuk
            </button>
        </form>
    </div>
@endsection