<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Octadecimal\Rental\Models\Rental;

class RentalReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Rental $rental) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Otrzymaliśmy Twoją rezerwację — MotoRent Demo',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.rental-received',
            with: [
                'rental' => $this->rental,
                'rentable' => $this->rental->rentable,
            ],
        );
    }
}
