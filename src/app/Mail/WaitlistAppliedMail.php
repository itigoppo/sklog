<?php

namespace App\Mail;

use App\Models\WaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistAppliedMail extends Mailable
{
  use Queueable, SerializesModels;

  public function __construct(
    public readonly WaitlistEntry $entry,
  ) {}

  public function envelope(): Envelope
  {
    return new Envelope(
      subject: '['.config('app.name').'] 新しい参加申請があります',
    );
  }

  public function content(): Content
  {
    return new Content(
      view: 'emails.waitlist_applied',
    );
  }
}
