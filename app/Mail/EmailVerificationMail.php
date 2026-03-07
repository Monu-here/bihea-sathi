<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public string $pin)
    {
    }

    public function build(): self
    {
        return $this->subject('Verify Your Email')
        ->view('emails.verify-email')
            ->with([
                'name' => $this->name,
                'pin' => $this->pin,
            ]);
    }
}
