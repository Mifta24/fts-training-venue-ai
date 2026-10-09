<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reference',
    'venue_id',
    'room_id',
    'conversation_id',
    'guest_name',
    'guest_email',
    'guest_phone',
    'contact_type',
    'locale',
    'organization',
    'event_title',
    'start_date',
    'end_date',
    'participants',
    'room_count',
    'seating_layout',
    'catering',
    'total_price',
    'status',
    'notes',
])]
class Booking extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Which status a booking may move to from its current one. Cancelled is
     * final: its rooms went back to the inventory and are not held any more.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_CANCELLED],
        self::STATUS_CANCELLED => [],
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'catering' => 'boolean',
            'total_price' => 'decimal:2',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * A short, unambiguous, non-sequential code organisers can quote to staff.
     */
    public static function generateReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $reference = 'TV-'.collect(range(1, 6))
                ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * The number of training days: both the first and the last day count.
     */
    public function days(): int
    {
        return (int) $this->start_date->diffInDays($this->end_date) + 1;
    }
}
