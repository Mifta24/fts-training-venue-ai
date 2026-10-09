<?php

namespace App\Notifications;

use App\Models\Booking;

/**
 * The few lines every organiser email repeats about the booking, in the organiser's language.
 */
class GuestBookingSummary
{
    /**
     * @return list<string>
     */
    public static function lines(Booking $booking, string $locale): array
    {
        $booking->loadMissing(['venue', 'room']);

        $labels = match ($locale) {
            'ja' => ['受付番号', 'ルーム', '初日', '最終日', '参加人数', '合計'],
            'id' => ['Referensi', 'Ruangan', 'Hari pertama', 'Hari terakhir', 'Peserta', 'Total'],
            default => ['Reference', 'Room', 'First day', 'Last day', 'Participants', 'Total'],
        };

        $dateFormat = $locale === 'ja' ? 'Y年n月j日' : 'j F Y';
        $format = fn ($date) => $date->locale($locale)->translatedFormat($dateFormat);

        return [
            "{$labels[0]}: {$booking->reference}",
            "{$labels[1]}: {$booking->room_count} × {$booking->room->translatedName($locale)}",
            "{$labels[2]}: {$format($booking->start_date)}",
            "{$labels[3]}: {$format($booking->end_date)}",
            "{$labels[4]}: {$booking->participants}",
            "{$labels[5]}: {$booking->venue->currency} ".number_format((float) $booking->total_price, 0, ',', '.'),
        ];
    }
}
