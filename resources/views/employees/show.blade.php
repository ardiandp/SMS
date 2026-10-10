@extends('layouts.app')

@section('title', $employee->full_name)

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">{{ $employee->full_name }}</h1>
        @can('update', $employee)
            <a href="{{ route('employees.edit', $employee) }}" class="rounded border border-indigo-600 text-indigo-600 px-3 py-1.5 text-sm hover:bg-indigo-50">Ubah</a>
        @endcan
    </div>

    <div class="bg-white rounded shadow p-6 max-w-xl">
        <dl class="text-sm space-y-1">
            <div><dt class="inline text-gray-500">No. Induk: </dt><dd class="inline">{{ $employee->employee_number }}</dd></div>
            <div><dt class="inline text-gray-500">Status: </dt><dd class="inline">{{ $employee->employment_status->label() }}</dd></div>
            <div><dt class="inline text-gray-500">Bergabung: </dt><dd class="inline">{{ $employee->joined_on?->format('d-m-Y') ?: '—' }}</dd></div>
            <div><dt class="inline text-gray-500">Keluar: </dt><dd class="inline">{{ $employee->left_on?->format('d-m-Y') ?: '—' }}</dd></div>
            <div><dt class="inline text-gray-500">Akun terhubung: </dt>
                <dd class="inline">{{ $employee->userLink?->user?->email ?: '—' }}</dd></div>
        </dl>

        <h2 class="font-medium mt-4 mb-2">Riwayat Penugasan</h2>
        <ul class="text-sm space-y-1">
            @forelse ($employee->assignments as $assignment)
                <li>{{ $assignment->role_title }} ({{ $assignment->starts_on?->format('d-m-Y') }} – {{ $assignment->ends_on?->format('d-m-Y') ?: 'sekarang' }})</li>
            @empty
                <li class="text-gray-500">Belum ada penugasan.</li>
            @endforelse
        </ul>
    </div>
@endsection