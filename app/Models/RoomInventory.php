<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The only source of truth for availability and price. Stands in for a real
 * booking-engine adapter for V1 — the AI must reach this table (or its
 * future adapter) through a tool call, never answer from its own memory.
 */
#[Fillable([
    'room_id',
    'event_date',
    'total_rooms',
    'booked_rooms',
    'price',
])]
class RoomInventory extends Model
{
    protected $table = 'room_inventory';

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'price' => 'decimal:2',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function availableRooms(): int
    {
        return max(0, $this->total_rooms - $this->booked_rooms);
    }

    public function isAvailable(): bool
    {
        return $this->availableRooms() > 0;
    }
}
