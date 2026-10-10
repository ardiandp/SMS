@php($guardian = $guardian ?? null)
@php($action = $action ?? ($guardian ? route('guardians.update', $guardian) : route('guardians.store')))

<form method="POST" action="{{ $action }}" class="bg-white rounded shadow p-6 max-w-xl space-y-4">
    @csrf

    <div>
        <label class="block text-sm font-medium mb-1">Nama Lengkap *</label>
        <input name="full_name" value="{{ old('full_name', $guardian?->full_name) }}" required
               class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Telepon</label>
        <input name="phone" value="{{ old('phone', $guardian?->phone) }}"
               class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Email</label>
        <input type="email" name="email" value="{{ old('email', $guardian?->email) }}"
               class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Alamat</label>
        <input name="address" value="{{ old('address', $guardian?->address) }}"
               class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
    </div>

    <button class="rounded bg-indigo-600 text-white px-4 py-2 text-sm hover:bg-indigo-700">Simpan</button>
</form>