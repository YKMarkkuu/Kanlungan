<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Must stay synchronous — queueing would serialize the plaintext code into the jobs table.
class OtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $plainCode,
        public string $email,
    ) {
    }

    /**
     * Get the message envelope. No reply-to by design; the sender comes from
     * the global MAIL_FROM_ADDRESS / MAIL_FROM_NAME config.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Kanlungan verification code',
        );
    }

    /**
     * Get the message content definition.
     * Plain Blade views only: an HTML part plus a plain-text part.
     */
    public function content(): Content
    {
        return (new Content(
            view: 'emails.otp-code',
            with: [
                'plainCode' => $this->plainCode,
                'email' => $this->email,
            ],
        ))->text('emails.otp-code-text');
    }
}
