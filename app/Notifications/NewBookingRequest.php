<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the venue team that an organiser asked to book a room.
 */
class NewBookingRequest extends Notification implements ShouldQueue
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
        $contact = $booking->guest_phone ?? $booking->guest_email;

        return (new MailMessage)
            ->subject("New booking request {$booking->reference} from {$booking->guest_name}")
            ->greeting("New booking request for {$booking->venue->name}")
            ->line("{$booking->guest_name}".($booking->organization ? " ({$booking->organization})" : '')." asked for {$booking->room_count} × {$booking->room->name}.")
            ->when($booking->event_title, fn (MailMessage $mail) => $mail->line("Programme: {$booking->event_title}"))
            ->line("From {$booking->start_date->toFormattedDateString()} to {$booking->end_date->toFormattedDateString()} ({$booking->days()} days).")
            ->line("Participants: {$booking->participants}".($booking->seating_layout ? ', set-up: '.str_replace('_', ' ', $booking->seating_layout) : '').($booking->catering ? ', with catering' : '').'.')
            ->line("Total: {$booking->venue->currency} ".number_format((float) $booking->total_price, 0, ',', '.').'.')
            ->line("Contact ({$booking->contact_type}): {$contact}")
            ->when($booking->notes, fn (MailMessage $mail) => $mail->line("Note: {$booking->notes}"))
            ->action('Review the request', route('admin.bookings.index', ['status' => Booking::STATUS_PENDING]));
    }
}
