<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an organiser that the team confirmed or cancelled their booking request.
 */
class BookingStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['venue', 'room']);
        $locale = $booking->locale ?? $booking->venue->default_locale;
        $copy = $this->copy($locale)[$booking->status];

        return (new MailMessage)
            ->subject(sprintf($copy['subject'], $booking->reference))
            ->greeting(sprintf($copy['greeting'], $booking->guest_name))
            ->line(sprintf($copy['intro'], $booking->venue->name))
            ->lines(GuestBookingSummary::lines($booking, $locale))
            ->line($copy['next'])
            ->salutation($booking->venue->name);
    }

    /**
     * @return array<string, array{subject: string, greeting: string, intro: string, next: string}>
     */
    private function copy(string $locale): array
    {
        return [
            'en' => [
                Booking::STATUS_CONFIRMED => [
                    'subject' => 'Your booking %s is confirmed',
                    'greeting' => 'Hello %s,',
                    'intro' => 'Good news: %s confirmed your booking request.',
                    'next' => 'Our team will contact you about set-up details, catering and payment.',
                ],
                Booking::STATUS_CANCELLED => [
                    'subject' => 'Your booking request %s was cancelled',
                    'greeting' => 'Hello %s,',
                    'intro' => 'We are sorry: %s could not keep this booking request.',
                    'next' => 'Please contact us if you would like other dates or another room.',
                ],
            ],
            'ja' => [
                Booking::STATUS_CONFIRMED => [
                    'subject' => 'ご予約が確定しました %s',
                    'greeting' => '%s 様',
                    'intro' => '%s がご予約リクエストを確定しました。',
                    'next' => 'レイアウトの詳細、ケータリング、お支払いについて、スタッフよりご連絡します。',
                ],
                Booking::STATUS_CANCELLED => [
                    'subject' => 'ご予約リクエストはキャンセルされました %s',
                    'greeting' => '%s 様',
                    'intro' => '申し訳ありません。%s ではこのご予約リクエストをお受けできませんでした。',
                    'next' => '別の日程やルームをご希望の場合はお問い合わせください。',
                ],
            ],
            'id' => [
                Booking::STATUS_CONFIRMED => [
                    'subject' => 'Booking %s dikonfirmasi',
                    'greeting' => 'Halo %s,',
                    'intro' => 'Kabar baik: %s telah mengonfirmasi permintaan booking Anda.',
                    'next' => 'Tim kami akan menghubungi Anda soal detail penataan ruang, katering, dan pembayaran.',
                ],
                Booking::STATUS_CANCELLED => [
                    'subject' => 'Permintaan booking %s dibatalkan',
                    'greeting' => 'Halo %s,',
                    'intro' => 'Mohon maaf, %s tidak dapat melanjutkan permintaan booking ini.',
                    'next' => 'Hubungi kami bila Anda ingin tanggal atau ruangan lain.',
                ],
            ],
        ][$locale] ?? $this->copy('en');
    }
}
