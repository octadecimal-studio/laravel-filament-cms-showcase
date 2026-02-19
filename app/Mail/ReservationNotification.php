<?php

declare(strict_types=1);

namespace App\Mail;

use App\Plugins\Reservations\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public ?string $motorcycleName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nowa rezerwacja - ' . $this->reservation->customer_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation-notification',
            with: [
                'reservation' => $this->reservation,
                'motorcycleName' => $this->motorcycleName,
            ],
        );
    }
}
