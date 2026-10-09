<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'venue_id',
    'name',
    'slug',
    'description',
    'translations',
    'room_type',
    'size_sqm',
    'floor_label',
    'capacity',
    'seating_layouts',
    'catering_included',
    'catering_available',
    'catering_price',
    'base_price',
    'amenities',
    'is_active',
    'sort_order',
])]
class Room extends Model
{
    use HasFactory, SoftDeletes;

    /** The ways a room can be set up, as stored in `seating_layouts`. */
    public const SEATING_LAYOUTS = ['theatre', 'classroom', 'u_shape', 'boardroom', 'banquet', 'cluster'];

    public const TYPES = ['classroom', 'workshop', 'boardroom', 'auditorium', 'studio'];

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'capacity' => 'integer',
            'seating_layouts' => 'array',
            'amenities' => 'array',
            'catering_included' => 'boolean',
            'catering_available' => 'boolean',
            'catering_price' => 'decimal:2',
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(RoomImage::class)->orderBy('sort_order');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(RoomInventory::class);
    }

    /**
     * How many participants fit in one room, in the given set-up. Without a
     * set-up (or one this room does not offer) it is the room's overall limit.
     */
    public function capacityFor(?string $layout = null): int
    {
        if ($layout !== null) {
            foreach ($this->seating_layouts ?? [] as $option) {
                if (($option['type'] ?? null) === $layout) {
                    return (int) $option['capacity'];
                }
            }
        }

        return (int) $this->capacity;
    }

    public function offersLayout(string $layout): bool
    {
        return collect($this->seating_layouts ?? [])->contains(fn (array $option) => ($option['type'] ?? null) === $layout);
    }

    public function translatedName(string $locale): string
    {
        $value = $this->translations[$locale]['name'] ?? null;

        return filled($value) ? $value : $this->name;
    }

    public function translatedDescription(string $locale): ?string
    {
        $value = $this->translations[$locale]['description'] ?? null;

        return filled($value) ? $value : $this->description;
    }
}
