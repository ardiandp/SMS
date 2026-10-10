@extends('layouts.app')

@section('title', 'Pilih Sekolah')

@section('content')
    <div class="max-w-xl mx-auto mt-10">
        <h1 class="text-xl font-semibold mb-2">Pilih Sekolah</h1>
        <p class="text-sm text-gray-600 mb-6">Anda hanya dapat memilih sekolah tempat Anda memiliki akses aktif.</p>

        @if ($schools->isEmpty())
            <div class="bg-white rounded shadow p-6 text-gray-600">
                Tidak ada sekolah yang dapat diakses. Hubungi administrator sekolah Anda.
            </div>
        @else
            <div class="space-y-3">
                @foreach ($schools as $school)
                    <form method="POST" action="{{ route('schools.switch', $school) }}"
                          class="bg-white rounded shadow p-4 flex items-center justify-between">
                        @csrf
                        <div>
                            <p class="font-medium">{{ $school->name }}</p>
                            <p class="text-sm text-gray-500">{{ $school->status->label() }}</p>
                        </div>
                        <button type="submit"
                                class="rounded bg-indigo-600 text-white px-4 py-2 text-sm hover:bg-indigo-700">
                            Pilih
                        </button>
                    </form>
                @endforeach
            </div>
        @endif
    </div>
@endsection