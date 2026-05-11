<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Octadecimal\Rental\Models\Rental;

class AdminRentalNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Rental $rental) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nowa rezerwacja — ' . ($this->rental->name ?? 'klient'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-rental-notification',
            with: [
                'rental' => $this->rental,
                'rentable' => $this->rental->rentable,
            ],
        );
    }
}
