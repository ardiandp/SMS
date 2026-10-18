@extends('layouts.app')

@section('title', $definition['label'])

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">{{ $definition['label'] }}</h1>
        <div class="flex gap-3 text-sm">
            <a href="{{ route('reports.export', $reportKey) }}?{{ http_build_query(array_merge($filters, ['columns' => $columns])) }}"
               class="text-indigo-600 hover:underline">Ekspor CSV</a>
            <a href="{{ route('reports.index') }}" class="text-gray-600 hover:underline">Semua Laporan</a>
        </div>
    </div>

    @error('report')
        <div class="mb-4 rounded bg-red-50 border border-red-200 text-red-800 px-4 py-3">{{ $message }}</div>
    @enderror

    <form method="GET" action="{{ route('reports.show', $reportKey) }}" class="bg-white border border-gray-200 rounded p-4 mb-4 space-y-3">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <label class="flex items-center gap-2">
                Dari
                <input type="date" name="from" value="{{ $filters['from'] }}" class="border border-gray-300 rounded px-2 py-1">
            </label>
            <label class="flex items-center gap-2">
                Sampai
                <input type="date" name="to" value="{{ $filters['to'] }}" class="border border-gray-300 rounded px-2 py-1">
            </label>
        </div>

        <div class="flex flex-wrap gap-4 text-sm">
            @foreach ($definition['columns'] as $key => $column)
                <label class="flex items-center gap-1">
                    <input type="checkbox" name="columns[]" value="{{ $key }}" @checked(in_array($key, $columns, true))>
                    {{ $column['label'] }}
                </label>
            @endforeach
        </div>

        <button type="submit" class="px-3 py-1 bg-indigo-600 text-white rounded text-sm">Terapkan Filter</button>
    </form>

    <div class="bg-white border border-gray-200 rounded overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    @foreach ($columns as $key)
                        <th class="text-left px-4 py-2 border-b border-gray-200 font-medium">
                            {{ $definition['columns'][$key]['label'] }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-b border-gray-100 last:border-0">
                        @foreach ($columns as $key)
                            <td class="px-4 py-2">{{ $definition['columns'][$key]['value']($row) ?? '—' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td class="px-4 py-4 text-gray-500" colspan="{{ max(count($columns), 1) }}">
                            Tidak ada data untuk filter ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $rows->links() }}
    </div>
@endsection
