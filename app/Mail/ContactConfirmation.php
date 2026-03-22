<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ContactMessage $contact,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '✅ Votre message a bien été reçu — ScolApp',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-confirmation',
        );
    }
}
