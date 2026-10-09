<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentVenue;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoomController extends Controller
{
    use ResolvesCurrentVenue;

    public function index(Request $request): View
    {
        $venue = $this->currentVenue($request);

        $rooms = $venue->rooms()->withCount('images')->orderBy('sort_order')->get();

        return view('admin.rooms.index', compact('venue', 'rooms'));
    }

    public function create(Request $request): View
    {
        $venue = $this->currentVenue($request);

        return view('admin.rooms.form', [
            'venue' => $venue,
            'room' => new Room(['room_type' => 'classroom', 'capacity' => 20, 'is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $venue = $this->currentVenue($request);
        $data = $this->validated($request);

        $room = $venue->rooms()->create($data);
        $this->syncImages($room, $request);

        return redirect()->route('admin.rooms.index')->with('status', "Room \"{$room->name}\" created.");
    }

    public function edit(Request $request, Room $room): View
    {
        $venue = $this->currentVenue($request);
        abort_if($room->venue_id !== $venue->id, 404);

        $room->load('images');

        return view('admin.rooms.form', compact('venue', 'room'));
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $venue = $this->currentVenue($request);
        abort_if($room->venue_id !== $venue->id, 404);

        $room->update($this->validated($request, $room));
        $this->syncImages($room, $request);

        return redirect()->route('admin.rooms.index')->with('status', "Room \"{$room->name}\" updated.");
    }

    public function destroy(Request $request, Room $room): RedirectResponse
    {
        $venue = $this->currentVenue($request);
        abort_if($room->venue_id !== $venue->id, 404);

        $openBookings = $room->bookings()
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
            ->whereDate('end_date', '>=', now($venue->timezone)->toDateString())
            ->count();

        if ($openBookings > 0) {
            return back()->with('error', "\"{$room->name}\" still has {$openBookings} pending or confirmed booking(s). Cancel them first, or hide the room instead.");
        }

        $room->delete();

        return redirect()->route('admin.rooms.index')->with('status', 'Room deleted.');
    }

    private function validated(Request $request, ?Room $room = null): array
    {
        $layoutRules = collect(Room::SEATING_LAYOUTS)
            ->mapWithKeys(fn (string $layout) => ["layout_{$layout}" => ['nullable', 'integer', 'min:1', 'max:1000']])
            ->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'translations.en.name' => ['nullable', 'string'],
            'translations.en.description' => ['nullable', 'string'],
            'translations.ja.name' => ['nullable', 'string'],
            'translations.ja.description' => ['nullable', 'string'],
            'room_type' => ['required', Rule::in(Room::TYPES)],
            'size_sqm' => ['nullable', 'integer', 'min:1'],
            'floor_label' => ['nullable', 'string', 'max:20'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            ...$layoutRules,
            'catering_included' => ['sometimes', 'boolean'],
            'catering_available' => ['sometimes', 'boolean'],
            'catering_price' => ['nullable', 'numeric', 'min:0'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'amenities' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $seatingLayouts = collect(Room::SEATING_LAYOUTS)
            ->filter(fn (string $layout) => filled($data["layout_{$layout}"] ?? null))
            ->map(fn (string $layout) => ['type' => $layout, 'capacity' => (int) $data["layout_{$layout}"]])
            ->values()
            ->all();

        $amenities = collect(explode(',', (string) ($data['amenities'] ?? '')))
            ->map(fn ($a) => Str::of($a)->trim()->slug('_')->toString())
            ->filter()
            ->values()
            ->all();

        return [
            'name' => $data['name'],
            'slug' => $room?->slug ?? $this->uniqueSlug($request->user()->currentVenue(), $data['name']),
            'description' => $data['description'] ?? null,
            'translations' => [
                'en' => ['name' => $data['translations']['en']['name'] ?? null, 'description' => $data['translations']['en']['description'] ?? null],
                'ja' => ['name' => $data['translations']['ja']['name'] ?? null, 'description' => $data['translations']['ja']['description'] ?? null],
            ],
            'room_type' => $data['room_type'],
            'size_sqm' => $data['size_sqm'] ?? null,
            'floor_label' => $data['floor_label'] ?? null,
            'capacity' => $data['capacity'],
            'seating_layouts' => $seatingLayouts,
            'catering_included' => $request->boolean('catering_included'),
            'catering_available' => $request->boolean('catering_available'),
            'catering_price' => $data['catering_price'] ?? null,
            'base_price' => $data['base_price'],
            'amenities' => $amenities,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    private function uniqueSlug($venue, string $name): string
    {
        $base = Str::slug($name) ?: 'room';
        $slug = $base;
        $suffix = 1;

        while ($venue->rooms()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function syncImages(Room $room, Request $request): void
    {
        $deleteIds = collect($request->input('delete_images', []))->map(fn ($id) => (int) $id);
        if ($deleteIds->isNotEmpty()) {
            $room->images()->whereIn('id', $deleteIds)->delete();
        }

        $newImages = collect($request->input('new_images', []))
            ->filter(fn ($row) => filled($row['url'] ?? null));

        $sort = $room->images()->max('sort_order') + 1;
        foreach ($newImages as $row) {
            $room->images()->create([
                'image_url' => $row['url'],
                'alt_text' => $row['alt'] ?? $room->name,
                'tags' => collect(explode(',', $row['tags'] ?? ''))->map(fn ($t) => trim($t))->filter()->values()->all(),
                'sort_order' => $sort++,
            ]);
        }
    }
}
