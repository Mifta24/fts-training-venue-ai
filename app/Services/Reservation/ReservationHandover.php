<?php

namespace App\Services\Reservation;

use App\Models\Booking;
use App\Models\Venue;

/**
 * Builds the WhatsApp / phone / email hand-over links that carry a
 * booking summary (or a plain "talk to the team" request) to the venue team.
 */
class ReservationHandover
{
    /**
     * @return array{whatsapp_url: ?string, phone_url: ?string, email_url: ?string}
     */
    public function forBooking(Venue $venue, Booking $booking, string $locale): array
    {
        $message = $this->reservationMessage($booking, $locale);

        return $this->links($venue, $message, $this->subject('reservation', $locale).' '.$booking->reference);
    }

    /**
     * @return array{whatsapp_url: ?string, phone_url: ?string, email_url: ?string}
     */
    public function forStaff(Venue $venue, string $locale): array
    {
        return $this->links($venue, $this->subject('staff_message', $locale), $this->subject('staff', $locale));
    }

    /**
     * @return array{whatsapp_url: ?string, phone_url: ?string, email_url: ?string}
     */
    private function links(Venue $venue, string $message, string $subject): array
    {
        $whatsapp = preg_replace('/\D+/', '', (string) $venue->whatsapp);
        $phone = preg_replace('/[^\d+]/', '', (string) $venue->phone);

        return [
            'whatsapp_url' => $whatsapp !== '' ? 'https://wa.me/'.$whatsapp.'?text='.rawurlencode($message) : null,
            'phone_url' => $phone !== '' ? 'tel:'.$phone : null,
            'email_url' => filled($venue->email) ? 'mailto:'.$venue->email.'?subject='.rawurlencode($subject).'&body='.rawurlencode($message) : null,
        ];
    }

    private function reservationMessage(Booking $booking, string $locale): string
    {
        $booking->loadMissing('room');

        $labels = match ($locale) {
            'en' => ['Hello, I would like to request a training room booking.', 'Name', 'First day', 'Last day', 'Participants', 'Rooms', 'Room', 'Programme', 'Special request', 'Reference'],
            'ja' => ['こんにちは。研修ルームの予約をリクエストしたいです。', 'お名前', '初日', '最終日', '参加人数', '部屋数', 'ルーム', 'プログラム名', 'ご要望', '受付番号'],
            default => ['Halo, saya ingin mengajukan permintaan booking ruang pelatihan.', 'Nama', 'Hari pertama', 'Hari terakhir', 'Peserta', 'Jumlah ruang', 'Ruangan', 'Nama acara', 'Permintaan khusus', 'Referensi'],
        };

        return implode("\n", [
            $labels[0],
            '',
            "{$labels[1]}: {$booking->guest_name}",
            "{$labels[2]}: ".$booking->start_date->locale($locale)->translatedFormat('j F Y'),
            "{$labels[3]}: ".$booking->end_date->locale($locale)->translatedFormat('j F Y'),
            "{$labels[4]}: {$booking->participants}",
            "{$labels[5]}: {$booking->room_count}",
            "{$labels[6]}: ".$booking->room->translatedName($locale),
            "{$labels[7]}: ".($booking->event_title ?: '-'),
            "{$labels[8]}: ".($booking->notes ?: '-'),
            '',
            "{$labels[9]}: {$booking->reference}",
        ]);
    }

    private function subject(string $key, string $locale): string
    {
        return [
            'en' => ['reservation' => 'Training room booking', 'staff' => 'Question for the venue team', 'staff_message' => 'Hello, I would like to speak with the venue team.'],
            'ja' => ['reservation' => '研修ルーム予約', 'staff' => 'スタッフへのご相談', 'staff_message' => 'こんにちは。会場のスタッフとお話ししたいです。'],
            'id' => ['reservation' => 'Booking ruang pelatihan', 'staff' => 'Pertanyaan untuk tim venue', 'staff_message' => 'Halo, saya ingin bicara dengan tim venue.'],
        ][$locale][$key];
    }
}
