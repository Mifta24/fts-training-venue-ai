<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The approved knowledge base the AI Coordinator retrieves from. This is the
 * ONLY place venue facts may come from — the model is never allowed to
 * answer a venue-knowledge question from its own memory.
 */
#[Fillable([
    'venue_id',
    'category',
    'title',
    'body',
    'translations',
    'tags',
    'image_url',
    'is_active',
    'sort_order',
])]
class VenueKnowledgeItem extends Model
{
    public const CATEGORY_GENERAL = 'general';

    public const CATEGORY_FACILITIES = 'facilities';

    public const CATEGORY_POLICIES = 'policies';

    public const CATEGORY_CATERING = 'catering';

    public const CATEGORY_TRANSPORT = 'transport';

    public const CATEGORY_FAQ = 'faq';

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'tags' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * A short icon name for this entry, taken from its tags or title so the
     * facilities cards can show a matching symbol without another column.
     */
    public function iconName(): string
    {
        $haystack = Str::lower($this->title.' '.implode(' ', $this->tags ?? []));

        foreach ([
            'projector' => ['projector', 'proyektor', 'screen', 'layar', 'display', 'av equipment', 'audio visual'],
            'sound' => ['sound', 'audio', 'microphone', 'mikrofon', 'speaker', 'suara'],
            'whiteboard' => ['whiteboard', 'flipchart', 'papan tulis', 'stationery', 'atk', 'training kit'],
            'wifi' => ['wifi', 'wi-fi', 'internet', 'bandwidth'],
            'dining' => ['catering', 'lunch', 'coffee break', 'makan', 'snack', 'dining', 'café', 'cafe', 'kafe'],
            'stay' => ['hotel', 'accommodation', 'penginapan', 'menginap', 'dormitory', 'asrama'],
            'parking' => ['parking', 'parkir', 'car park', 'bus'],
            'transit' => ['mrt', 'train', 'kereta', 'station', 'stasiun', 'transit', 'transjakarta'],
            'transport' => ['airport', 'bandara', 'transfer', 'shuttle', 'antar-jemput'],
            'support' => ['technician', 'teknisi', 'support', 'event crew', 'on-site', 'registration'],
            'security' => ['security', 'keamanan', 'access card', 'kartu akses'],
            'work' => ['breakout', 'discussion', 'lounge', 'co-working', 'coworking', 'workspace'],
            'place' => ['nearby', 'terdekat', 'neighbourhood', 'lingkungan', 'attraction'],
        ] as $icon => $needles) {
            foreach ($needles as $needle) {
                if (Str::contains($haystack, $needle)) {
                    return $icon;
                }
            }
        }

        return 'star';
    }

    public function translatedTitle(string $locale): string
    {
        $value = $this->translations[$locale]['title'] ?? null;

        return filled($value) ? $value : $this->title;
    }

    public function translatedBody(string $locale): string
    {
        $value = $this->translations[$locale]['body'] ?? null;

        return filled($value) ? $value : $this->body;
    }
}
