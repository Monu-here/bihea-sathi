<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name)
    {
    }

    public function build(): self
    {
        return $this->subject('Welcome to Bihea Sathi')
        ->view('emails.welcome')
            ->with([
                'name' => $this->name,
            ]);
    }
}
