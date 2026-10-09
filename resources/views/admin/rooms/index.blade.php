<x-admin-layout title="Rooms">
    <x-slot name="actions">
        <a href="{{ route('admin.rooms.create') }}" class="rounded-lg bg-ink px-3 py-2 text-sm font-medium text-white hover:bg-signal hover:text-signal-ink">
            Add room
        </a>
    </x-slot>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-xs uppercase text-stone-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Day rate</th>
                    <th class="px-4 py-3">Capacity</th>
                    <th class="px-4 py-3">Images</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($rooms as $room)
                    <tr>
                        <td class="px-4 py-3 font-medium text-stone-900">{{ $room->name }}</td>
                        <td class="px-4 py-3">{{ Str::headline($room->room_type) }} @if($room->floor_label)<span class="text-stone-400">· {{ $room->floor_label }}</span>@endif @if($room->size_sqm)<span class="text-stone-400">· {{ $room->size_sqm }} m²</span>@endif</td>
                        <td class="px-4 py-3">{{ $venue->currency }} {{ number_format((float) $room->base_price, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">{{ $room->capacity }}</td>
                        <td class="px-4 py-3">{{ $room->images_count }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $room->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                {{ $room->is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.rooms.inventory.index', $room) }}" class="text-stone-600 hover:underline">Availability</a>
                            <a href="{{ route('admin.rooms.edit', $room) }}" class="ml-3 text-stone-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.rooms.destroy', $room) }}" class="inline" onsubmit="return confirm('Delete this room?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-stone-400">No rooms yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
