@php
    $isEdit = $room->exists;
    $layoutCapacity = collect($room->seating_layouts ?? [])->pluck('capacity', 'type');
@endphp

<x-admin-layout :title="$isEdit ? 'Edit room' : 'Add room'">
    <form method="POST" action="{{ $isEdit ? route('admin.rooms.update', $room) : route('admin.rooms.store') }}" class="space-y-8">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Basics</h2>
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-stone-700">Name (Indonesian, default)</label>
                    <input type="text" name="name" value="{{ old('name', $room->name) }}" required
                        class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-stone-700">Description (Indonesian, default)</label>
                    <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('description', $room->description) }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Name (English)</label>
                    <input type="text" name="translations[en][name]" value="{{ old('translations.en.name', $room->translations['en']['name'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Name (Japanese)</label>
                    <input type="text" name="translations[ja][name]" value="{{ old('translations.ja.name', $room->translations['ja']['name'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Description (English)</label>
                    <textarea name="translations[en][description]" rows="2" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('translations.en.description', $room->translations['en']['description'] ?? '') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Description (Japanese)</label>
                    <textarea name="translations[ja][description]" rows="2" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('translations.ja.description', $room->translations['ja']['description'] ?? '') }}</textarea>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Size & capacity</h2>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700">Room type</label>
                    <select name="room_type" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                        @foreach (\App\Models\Room::TYPES as $type)
                            <option value="{{ $type }}" @selected(old('room_type', $room->room_type) === $type)>{{ Str::headline($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Size (m²)</label>
                    <input type="number" name="size_sqm" value="{{ old('size_sqm', $room->size_sqm) }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Location / floor</label>
                    <input type="text" name="floor_label" value="{{ old('floor_label', $room->floor_label) }}" placeholder="Lt. 2" maxlength="20" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Max capacity</label>
                    <input type="number" name="capacity" value="{{ old('capacity', $room->capacity) }}" required min="1" max="1000" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
            </div>
            <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-stone-500">Capacity per seating layout (leave empty if not offered)</h3>
            <div class="mt-2 grid grid-cols-2 gap-4 sm:grid-cols-3">
                @foreach (\App\Models\Room::SEATING_LAYOUTS as $layout)
                    <div>
                        <label class="block text-sm font-medium text-stone-700">{{ Str::headline($layout) }}</label>
                        <input type="number" name="layout_{{ $layout }}" value="{{ old('layout_'.$layout, $layoutCapacity[$layout] ?? '') }}" min="1" max="1000" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Pricing & inclusions</h2>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700">Base price / day</label>
                    <input type="number" step="1000" name="base_price" value="{{ old('base_price', $room->base_price) }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Catering / participant / day</label>
                    <input type="number" step="1000" name="catering_price" value="{{ old('catering_price', $room->catering_price) }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Sort order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $room->sort_order) }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-6">
                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="catering_included" value="1" @checked(old('catering_included', $room->catering_included)) class="rounded border-stone-300">
                    Lunch & coffee breaks included in the rate
                </label>
                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="catering_available" value="1" @checked(old('catering_available', $room->catering_available)) class="rounded border-stone-300">
                    Catering can be added
                </label>
                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $room->is_active)) class="rounded border-stone-300">
                    Published (visible on the website)
                </label>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium text-stone-700">Equipment (comma-separated)</label>
                <input type="text" name="amenities" value="{{ old('amenities', implode(', ', $room->amenities ?? [])) }}" placeholder="projector, screen, whiteboard, wifi, sound_system"
                    class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Photos</h2>

            @if ($isEdit && $room->images->isNotEmpty())
                <div class="mt-4 space-y-2">
                    @foreach ($room->images as $image)
                        <div class="flex items-center gap-3 rounded-lg border border-stone-200 p-2">
                            <img src="{{ $image->image_source }}" alt="{{ $image->alt_text }}" class="h-14 w-20 rounded object-cover">
                            <div class="flex-1 text-xs text-stone-500">
                                <p class="truncate">{{ $image->image_url }}</p>
                                <p>Tags: {{ implode(', ', $image->tags ?? []) }}</p>
                            </div>
                            <label class="flex items-center gap-1 text-xs text-red-600">
                                <input type="checkbox" name="delete_images[]" value="{{ $image->id }}" class="rounded border-stone-300">
                                Delete
                            </label>
                        </div>
                    @endforeach
                </div>
            @endif

            <p class="mt-4 text-xs text-stone-500">Add photos by URL (e.g. an Unsplash or CDN link). Tag them so the AI Coordinator can pick the right one — e.g. "stage, theatre" or "projector, screen".</p>
            @for ($i = 0; $i < 3; $i++)
                <div class="mt-2 grid grid-cols-6 gap-2">
                    <input type="url" name="new_images[{{ $i }}][url]" placeholder="https://…" class="col-span-3 rounded-lg border border-stone-300 px-3 py-2 text-sm">
                    <input type="text" name="new_images[{{ $i }}][tags]" placeholder="classroom, projector" class="col-span-2 rounded-lg border border-stone-300 px-3 py-2 text-sm">
                    <input type="text" name="new_images[{{ $i }}][alt]" placeholder="Alt text" class="rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
            @endfor
        </section>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.rooms.index') }}" class="rounded-lg border border-stone-300 px-4 py-2 text-sm text-stone-600 hover:bg-stone-50">Cancel</a>
            <button type="submit" class="rounded-lg bg-ink px-4 py-2 text-sm font-medium text-white hover:bg-signal hover:text-signal-ink">Save room</button>
        </div>
    </form>
</x-admin-layout>
